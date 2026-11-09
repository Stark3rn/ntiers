# ntiers

Projet d'infrastructure n-tiers réalisé par **Alain**, **Celestin** et **Lilian**.

Ce dépôt contient toute la documentation et la configuration du projet : un cluster Kubernetes ([k3s](https://k3s.io/)) réparti sur trois machines reliées par [Tailscale](https://tailscale.com/), qui héberge les services exposés sous le domaine `riz-goler.fr`.

## Arborescence

```
.
├── docs/         # Documentation : infrastructure, répartition des tâches, runbooks, diagrammes
└── src/
    ├── config-ubuntu.sh   # Configuration post-install d'une machine (exécution locale)
    ├── ansible/           # Équivalent Ansible de config-ubuntu.sh (exécution à distance)
    └── kubernetes/        # Helmfiles + values.yaml de chaque service du cluster
```

## Documentation

| Document                                              | Contenu                                                  |
|:------------------------------------------------------|:---------------------------------------------------------|
| [infra.md](docs/infra.md)                             | Machines, système, réseau, cluster et services           |
| [k3s-cluster.md](docs/k3s-cluster.md)                 | Installation k3s sur Tailscale, vérifications, dépannage |
| [repartition_taches.md](docs/repartition_taches.md)   | Qui configure quel service                               |
| [diagrams/](docs/diagrams/)                           | Schémas matériel et services (sources `.drawio` + `.svg`) |

## Équipe

| Membre   | Machine                  | Hostname        |
|:---------|:-------------------------|:----------------|
| Alain    | HP Victus Laptop (VM)    | `kbs`           |
| Celestin | Lenovo Thinkpad P50 (VM) | `kube`          |
| Lilian   | HP Laptop                | `ntiers-lilian` |
