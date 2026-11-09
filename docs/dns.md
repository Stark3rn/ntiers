# DNS : carte des noms de domaine et des IP

Toutes les résolutions du domaine `riz-goler.fr` sont servies par **BIND** (namespace `dns`, chart [`charts/bind`](../src/kubernetes/charts/bind), zone dans [`values/dns/bind/values.yaml`](../src/kubernetes/values/dns/bind/values.yaml)).

## Règle

**Un service qui en appelle un autre utilise son nom de domaine `*.riz-goler.fr`, jamais une IP ni un `*.svc.cluster.local`.**

Chemin d'une requête depuis un pod : `pod -> CoreDNS (kube-dns) -> BIND` (ConfigMap `coredns-custom`, `forward` de `riz-goler.fr` vers la ClusterIP de BIND). Les ClusterIP des Services sont **figées en dur** dans les `values.yaml`, pour que l'enregistrement BIND ne devienne jamais faux.

Deux familles d'adresses :

- **IP de nœud Tailscale** : joignables depuis les postes. Réservées à BIND et aux sites HTTP servis par Traefik (`web`, `nfs`).
- **ClusterIP** : joignables uniquement depuis les pods et les nœuds du cluster.

| Nœud            | IP Tailscale    |
|:----------------|:---------------:|
| `ntiers-lilian` | 100.116.195.94  |
| `kube`          | 100.117.159.127 |
| `kbs`           | 100.108.118.15  |

## Carte nom -> IP

| Nom                    | IP                              | Port(s)      | Cible                          | IP figée dans                              |
|:-----------------------|:--------------------------------|:-------------|:-------------------------------|:-------------------------------------------|
| `ns1`                  | 100.117.159.127                 | 53           | BIND (nœud `kube`)             | -                                          |
| `dns`                  | 100.117.159.127                 | 53           | BIND (hostPort, nœud `kube`)   | -                                          |
| `web`                  | 100.116.195.94, 100.117.159.127 | 80           | Traefik -> ingress `nginx-lb`  | -                                          |
| `nfs`                  | 100.116.195.94, 100.117.159.127 | 80           | Traefik -> ingress `apache`    | -                                          |
| `nlb`                  | 10.43.96.37                     | 80           | Service `nginx-lb`             | `values/core-web/nginx-lb`                 |
| `php`                  | 10.43.89.247                    | 9000         | Service `php-fpm`              | `values/core-web/php-fpm`                  |
| `apache`               | 10.43.189.143                   | 80           | Service `apache`               | `values/static-web/apache`                 |
| `nfsd`                 | 10.43.26.41                     | 2049         | Service `nfs-server`           | `values/static-web/nfs-server`             |
| `hap`                  | 10.43.39.92                     | 27017, 6379  | Service `haproxy`              | `values/storage/haproxy`                   |
| `mdb`                  | 10.43.39.92                     | 27017        | HAProxy (voir ci-dessous)      | `values/storage/haproxy`                   |
| `rd`                   | 10.43.161.29                    | 6379         | Service `redis-master`         | `values/storage/redis` (`master.service`)  |
| `log`                  | 10.43.216.135                   | 5044, 5514   | Service `logstash`             | hors dépôt, à figer                        |
| `kiba`                 | 10.43.216.172                   | 5601         | Service `kibana`               | hors dépôt, à figer                        |
| `trivy`                | 10.43.6.93                      | 4954         | Service `trivy-server`         | hors dépôt, à figer                        |
| `ca`                   | 10.43.65.160                    | 9000         | Service `stepca`               | hors dépôt, à figer                        |
| `wzh`                  | 10.43.17.26                     | 1514, 1515, 55000 | Service `wazuh-ts`        | hors dépôt, à figer                        |
| `bind` (pour CoreDNS)  | 10.43.76.108                    | 53           | Service `bind`                 | `values/dns/bind` (`service.clusterIP`)    |

Noms de [repartition_taches.md](repartition_taches.md) sans enregistrement, car non déployés : `alb`, `vrn`, `sqd`, `kclk`, `captcha`, `post`, `mhg`, `up`. `elastic` n'en a pas non plus : `elasticsearch` est un Service headless, sans IP stable.

Noms ajoutés, absents du tableau de répartition : `php` (`web.*` est pris par l'ingress), `apache` (`nfs.*` est pris par l'ingress) et `nfsd` (serveur NFS).

## Qui appelle qui (par nom)

| Appelant   | Appelé                  | Nom utilisé                | Configuré dans                             |
|:-----------|:------------------------|:---------------------------|:-------------------------------------------|
| `nginx-lb` | `php-fpm`               | `php.riz-goler.fr:9000`    | `values/core-web/nginx-lb` (`phpFpm.host`) |
| `php-fpm`  | HAProxy (Redis)         | `hap.riz-goler.fr:6379`    | `values/core-web/php-fpm` (`REDIS_HOST`)   |
| `php-fpm`  | HAProxy (MongoDB)       | `hap.riz-goler.fr:27017`   | `values/core-web/php-fpm` (`MONGODB_URI`)  |
| `php-fpm`  | contenu statique        | `nfs.riz-goler.fr`         | `values/core-web/php-fpm` (`STATIC_BASE_URL`) |
| HAProxy    | Redis                   | `rd.riz-goler.fr:6379`     | `values/storage/haproxy` (`be_redis`)      |
| CoreDNS    | BIND                    | IP `10.43.76.108`          | `charts/bind` (`coredns-custom`)           |

## Exceptions (IP ou nom Kubernetes conservés)

1. **CoreDNS -> BIND** : la directive `forward` n'accepte qu'une IP.
2. **Montage NFS d'Apache** : le kubelet monte le volume depuis l'hôte du nœud, dont le résolveur ne passe pas par BIND. Le PV utilise l'IP `10.43.26.41`. À remplacer par `nfsd.riz-goler.fr` quand le split DNS Tailscale sera en place sur les nœuds.
3. **HAProxy -> pods MongoDB** : les membres du replicaset sont des pods d'un StatefulSet (`mongodb-N.mongodb-headless...`). Leur IP est éphémère et leur identité est un nom Kubernetes que MongoDB utilise en interne. Ils ne peuvent pas être figés.
4. **`mdb`** : MongoDB n'a pas de Service non-headless, donc `mdb` pointe sur HAProxy:27017, par lequel passe tout le trafic MongoDB.

## Ajouter ou modifier un service

1. Figer la ClusterIP dans le `values.yaml` du service (`service.clusterIP`, ou la clé équivalente du chart). Choisir une IP libre de `10.43.0.0/16` : `kubectl get svc -A -o custom-columns=NS:.metadata.namespace,NAME:.metadata.name,IP:.spec.clusterIP`.
2. Ajouter l'enregistrement dans `zone.records` de `values/dns/bind/values.yaml`.
3. **Incrémenter `zone.serial`** puis `helmfile -f helmfiles/dns/bind/helmfile.yaml apply` (le pod BIND redémarre tout seul, la config est checksumée).
4. Mettre à jour la carte ci-dessus.
5. Le champ `spec.clusterIP` d'un Service est immuable : pour changer une IP existante, supprimer le Service avant de réappliquer.