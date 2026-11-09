# Configuration du cluster k3s

Les trois machines sont dans des réseaux différents (Wi‑Fi domestique, VM...) et ne se joignent que par **Tailscale**. Toute la communication du cluster doit donc passer par l'interface `tailscale0`. Sans cela, le cluster semble fonctionner mais les pods de nœuds différents ne se parlent pas.

> Rôles et IP : voir [infra.md](infra.md).

## Règle d'or

Sur **chaque** nœud (master et workers), `/etc/rancher/k3s/config.yaml` doit contenir :

```yaml
node-ip: <IP Tailscale du nœud>        # tailscale ip -4
flannel-iface: tailscale0
```

- `node-ip` : l'IP annoncée au cluster. C'est elle que le master utilise pour joindre le kubelet (port 10250) du nœud.
- `flannel-iface` : l'interface sur laquelle Flannel monte son tunnel VXLAN (UDP 8472) entre les pods.

Le fichier est lu par `k3s` (master) et `k3s-agent` (workers). Il doit exister **avant** la première installation ; sinon, le fichier + redémarrage du service suffisent.

## Installation

Prérequis : Tailscale connecté (`tailscale status`) et activé au boot (`systemctl is-enabled tailscaled`).

### Master (`kube`)

```bash
sudo mkdir -p /etc/rancher/k3s
printf 'node-ip: %s\nflannel-iface: tailscale0\n' "$(tailscale ip -4)" | sudo tee /etc/rancher/k3s/config.yaml
curl -sfL https://get.k3s.io | sh -
sudo cat /var/lib/rancher/k3s/server/node-token     # token pour les workers
```

### Workers (`ntiers-lilian`, `kbs`)

```bash
sudo mkdir -p /etc/rancher/k3s
printf 'node-ip: %s\nflannel-iface: tailscale0\n' "$(tailscale ip -4)" | sudo tee /etc/rancher/k3s/config.yaml
curl -sfL https://get.k3s.io | K3S_URL=https://100.117.159.127:6443 K3S_TOKEN=<token> sh -
```

`K3S_URL` pointe sur l'IP Tailscale du master.

### Corriger un nœud déjà installé

```bash
# master
sudo systemctl restart k3s
# worker
sudo systemctl restart k3s-agent
```

Puis supprimer les pods qui se sont bloqués pendant la coupure (voir « Pièges »).

## Vérifications

```bash
kubectl get nodes -o wide        # INTERNAL-IP en 100.x pour TOUS les nœuds
ip -d link show flannel.1        # "local 100.x.x.x dev tailscale0"
kubectl logs -n <ns> <pod>       # doit marcher sur un pod de chaque nœud
```

## Symptômes d'un réseau inter-nœuds cassé

| Symptôme | Explication |
|:---------|:------------|
| `kubectl logs` / `exec` : `502 Bad Gateway` en visant `192.168.x.x:10250` | Le master joint le kubelet par l'IP LAN du worker au lieu de l'IP Tailscale |
| `flannel.1` avec `local 192.168.x.x dev wlp1s0` | Flannel a choisi l'interface Wi‑Fi : le tunnel VXLAN ne traverse pas Internet |
| Pods `0/1` en `Running`, probes en `timed out`, DNS interne qui expire | Le pod ne peut pas joindre CoreDNS ou un pair situé sur un autre nœud |
| Seuls les pods qui n'ont besoin que de leur propre nœud fonctionnent | Idem |

## Pièges

- **Données persistantes et jonction au replica set.** Les images Bitnami MongoDB sautent la configuration du replica set quand elles trouvent des données (`Deploying MongoDB with persisted data...`). Si un membre a démarré pendant une coupure réseau, il ne retentera pas de rejoindre le primary, même une fois le réseau réparé. Il faut repartir d'un volume vide. Pour un secondaire qui n'a jamais synchronisé :
  ```bash
  kubectl delete pvc datadir-mongodb-1 -n storage --wait=false
  kubectl delete pod mongodb-1 -n storage
  kubectl delete pod mongodb-arbiter-0 -n storage   # son emptyDir survit aux redémarrages du conteneur
  ```
  Ne jamais supprimer le PVC du primary (`datadir-mongodb-0`).
  Vérification : `rs.status().members` doit lister PRIMARY, SECONDARY et ARBITER.
- **Probes dépendantes du réseau.** La liveness probe des replicas Redis (`ping_liveness_local_and_master.sh`) contacte aussi le master : une coupure inter-nœuds fait redémarrer les replicas. C'est attendu, et ça se résorbe tout seul une fois le réseau rétabli.
- **Probes Mongo et mémoire.** Les probes MongoDB lancent `mongosh` (Node.js) *dans le conteneur*, ce qui consomme de la mémoire du cgroup du pod. Une limite trop serrée provoque des `OOMKilled` (voir les limites dans [`values/storage/mongodb/values.yaml`](../src/kubernetes/values/storage/mongodb/values.yaml)).
- **HAProxy et DNS au démarrage.** HAProxy quitte s'il ne résout pas un backend au boot. Les `server` utilisent `resolvers kube init-addr none` pour démarrer même si un pod n'existe pas encore ; le résolveur pointe sur l'IP de `kube-dns` (`kubectl get svc -n kube-system kube-dns`, 10.43.0.10 par défaut). Voir [`values/storage/haproxy/values.yaml`](../src/kubernetes/values/storage/haproxy/values.yaml).
- **Horloges.** Les `<invalid>` dans la colonne AGE de `kubectl get pods` indiquent des horodatages dans le futur par rapport à la machine qui lance `kubectl` : vérifier `timedatectl` (« System clock synchronized: yes ») sur chaque nœud.
- **Tailscale au boot.** Si `tailscaled` ne démarre pas avant k3s, le nœud est injoignable pour le cluster : `sudo systemctl enable tailscaled`.
