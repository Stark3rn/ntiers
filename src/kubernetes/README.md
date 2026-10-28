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

## Storage

MongoDB (replicaset, 2 nœuds + 1 arbitre) et Redis (1 master + 2 replicas) sont exposés en interne au cluster via un **HAProxy** qui fait office de load balancer TCP devant les deux - cf. [`values/storage/haproxy/values.yaml`](values/storage/haproxy/values.yaml) pour le détail des frontends/backends, et les limites de ce type de LB face à des bases répliquées (pas de détection du primary).

## Static Web Server

Un serveur **NFS** interne au cluster (`nfs-server`) exporte un volume, monté comme docroot par des pods **Apache** (`apache`) - ceux-ci se contentent de servir ce qui a été déposé sur le partage. Le contenu (actuellement le site statique `riz-goler` : `about.html`, logos, CSS) est injecté au démarrage par un initContainer à partir d'un ConfigMap défini dans [`values/static-web/apache/values.yaml`](values/static-web/apache/values.yaml) - source "propre" de ces fichiers : [`src/static/riz-goler/`](../static/riz-goler/).

Le type de volume Kubernetes `nfs` exige le paquet `nfs-common` sur les nœuds qui montent ce volume (installé par les scripts de config)

## Core Web Application

NGINX LB (`nginx-lb`) termine le HTTP et forwarde tout en FastCGI vers l'application PHP-FPM (`php-fpm`), qui accède à Redis/MongoDB via le HAProxy de la catégorie storage. Image PHP à construire depuis [`src/docker/php-fpm/`](../docker/php-fpm/), c'est l'app **riz-goler** : un site de blagues sans compte. La page "à propos" et les images vivent côté static-web.

NGINX n'a pas de copie du code applicatif : tout est forwardé en FastCGI vers un front controller unique (`/app/public/index.php`), c'est l'app PHP qui route selon `REQUEST_URI`.
