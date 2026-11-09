# Helmfiles du cluster ntiers

Arborescence destinée à accueillir, pour chaque service installé dans le cluster k3s, sa définition d'installation (`helmfiles/`) et sa configuration (`values/`) - deux dossiers séparés, sur le même principe qu'un `values.yaml` externalisé d'un chart Helm.

```
kubernetes/
├── charts/                 # charts Helm locaux, pour notre propre code (pas de chart vendeur)
│   └── <service>/
├── helmfiles/              # "quoi installer" : repo/chart local + version
│   └── <catégorie>/
│       └── <service>/
│           └── helmfile.yaml
└── values/                 # "comment le configurer" : values.yaml du chart
    └── <catégorie>/
        └── <service>/
            └── values.yaml
```

- `<catégorie>` reprend les catégories de [docs/infra.md](../../docs/infra.md).
- Pour un service tiers, `helmfile.yaml` référence un chart vendeur publié. Pour notre propre code applicatif, `helmfile.yaml` référence un chart local sous `charts/`.
- Chaque `helmfile.yaml` référence son `values.yaml` par chemin relatif, et peut être appliqué indépendamment : `helmfile -f helmfiles/<catégorie>/<service>/helmfile.yaml apply`.
- [`helmfiles/helmfile.yaml`](helmfiles/helmfile.yaml) agrège tous les services déclarés, dans l'ordre où ils doivent être installés. `helmfile -f helmfiles/helmfile.yaml apply` installe/mets à jour tout le cluster en une fois.

## DNS (BIND)

Tous les services s'appellent entre eux par leur nom `*.riz-goler.fr`, résolu par **BIND** (chart [`charts/bind`](charts/bind), zone dans [`values/dns/bind/values.yaml`](values/dns/bind/values.yaml)). Les ClusterIP des Services sont figées en dur dans chaque `values.yaml` pour rester alignées avec la zone. Carte nom -> IP, exceptions et procédure d'ajout d'un service : [docs/dns.md](../../docs/dns.md).

## Storage

MongoDB (replicaset, 2 nœuds + 1 arbitre) et Redis (1 master + 2 replicas) sont exposés en interne au cluster via un **HAProxy** qui fait office de load balancer TCP devant les deux - cf. [`values/storage/haproxy/values.yaml`](values/storage/haproxy/values.yaml) pour le détail des frontends/backends, et les limites de ce type de LB face à des bases répliquées (pas de détection du primary).

## Static Web Server

Un serveur **NFS** interne au cluster (`nfs-server`) exporte un volume, monté comme docroot par des pods **Apache** (`apache`) - ceux-ci se contentent de servir ce qui a été déposé sur le partage. Le contenu (actuellement le site statique `riz-goler` : `about.html`, logos, CSS) est injecté au démarrage par un initContainer à partir d'un ConfigMap défini dans [`values/static-web/apache/values.yaml`](values/static-web/apache/values.yaml) - source "propre" de ces fichiers : [`src/static/riz-goler/`](../static/riz-goler/).

Le type de volume Kubernetes `nfs` exige le paquet `nfs-common` sur les nœuds qui montent ce volume (installé par les scripts de config)

### Dépannage : pod Apache bloqué en `Init:0/1`

Le montage NFS est fait par le **kubelet, sur l'hôte du nœud** (pas dans un pod), tant que le volume n'est pas monté, l'initContainer ne démarre pas.
Diagnostic : `kubectl describe pod -n static-web <pod-apache>`, section *Events* (`FailedMount`).

Cause fréquente : `Failed to resolve server ...`. Le kubelet monte depuis l'hôte, qui ne résout ni `*.svc.cluster.local` ni forcément `riz-goler.fr`. Le PV pointe donc sur la **ClusterIP figée** du `nfs-server` (`10.43.26.41`, définie dans les deux values). Le champ `spec.nfs.server` d'un PV est immuable : après modification, supprimer le PV/PVC avant de réappliquer (`kubectl delete pvc -n static-web apache-nfs-pvc; kubectl delete pv apache-nfs-pv`). Les données restent dans le PVC du `nfs-server`.


## Core Web Application

NGINX LB (`nginx-lb`) termine le HTTP et forwarde tout en FastCGI vers l'application PHP-FPM (`php-fpm`), qui accède à Redis/MongoDB via le HAProxy de la catégorie storage. Image PHP à construire depuis [`src/docker/php-fpm/`](../docker/php-fpm/), c'est l'app **riz-goler** : un site de blagues sans compte. La page "à propos" et les images vivent côté static-web.

NGINX n'a pas de copie du code applicatif : tout est forwardé en FastCGI vers un front controller unique (`/app/public/index.php`), c'est l'app PHP qui route selon `REQUEST_URI`.

### Image PHP-FPM (GitHub Container Registry)

L'image est publiée sur `ghcr.io/stark3rn/ntiers/php-fpm` (cf. [`values/core-web/php-fpm/values.yaml`](values/core-web/php-fpm/values.yaml)). Tous les nœuds la téléchargent eux-mêmes : plus aucun import manuel.

- **Build automatique** : le workflow [`.github/workflows/php-fpm-image.yml`](../../.github/workflows/php-fpm-image.yml) construit et pousse l'image (`:latest` et `:<sha du commit>`) à chaque push sur `main` qui modifie `src/docker/php-fpm/`. Il peut aussi être lancé à la main (onglet *Actions* > *Run workflow*).
- **Premier push** : le paquet est créé **privé** par défaut. Le passer en public (GitHub > profil/organisation > *Packages* > `ntiers/php-fpm` > *Package settings* > *Change visibility*), sinon les nœuds obtiennent un `ImagePullBackOff` (`401`/`403`).
- **Déployer une nouvelle version** (`pullPolicy: Always` retélécharge `:latest` à chaque démarrage de pod) :
  ```bash
  kubectl rollout restart deployment/php-fpm -n core-web
  kubectl get pods -n core-web -o wide
  ```
- **Build local** (test) : `cd src/docker/php-fpm && docker build -t ghcr.io/stark3rn/ntiers/php-fpm:latest .`
- **Si on garde le paquet privé** : créer un secret `docker-registry` dans `core-web` (token avec `read:packages`) et ajouter `imagePullSecrets` au Deployment du chart.

