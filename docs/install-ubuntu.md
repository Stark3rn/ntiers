# Installation d'Ubuntu Server

Étapes suivies dans l'installeur **Ubuntu Server** (Subiquity) pour chacune des machines du cluster `ntiers`.

> Une fois l'installation terminée, passer à la configuration post-install : [`config-ubuntu.sh`](../src/config-ubuntu.sh) ou le playbook [`config_ubuntu.yml`](../src/ansible/playbooks/config_ubuntu.yml) (port SSH, Tailscale...).

## Résumé

| Étape                    | Choix                          |
|:-------------------------|:-------------------------------|
| Langue                   | English                        |
| Disposition clavier      | French (variante French)       |
| Profil d'installation    | Ubuntu Server (normal)         |
| Disque                   | Disque entier, formaté en LVM  |
| Serveur SSH              | OpenSSH server installé        |
| Ubuntu Pro               | Ignoré                         |

## 1. Langue et clavier

| Option    | Valeur  |
|:----------|:--------|
| Langue    | English |
| Disposition | French |
| Variante  | French  |

## 2. Profil d'installation

Choisir **Ubuntu Server (normal)**.

## 3. Configuration du disque

- Utiliser le disque entier (*Use an entire disk*)
- Configurer ce disque en groupe **LVM**

## 4. Identité de la machine

| Champ      | Valeur       |
|:-----------|:-------------|
| Nom        | `ntiers`     |
| Hostname   | `ntiers-XXX` |
| Username   | `XXXX`       |
| Password   | `12341234`   |

> `XXX` et `XXXX` sont à remplacer par le nom réel de la machine/de l'utilisateur.

## 5. Ubuntu Pro

Ignorer cette étape (*Skip Ubuntu Pro*).

## 6. Serveur SSH

Cocher **Install OpenSSH server** dans l'écran de sélection des fonctionnalités.

## Étape suivante

La machine redémarre sur un système Ubuntu Server minimal, accessible en SSH sur le port par défaut (22). La suite de la configuration (port SSH dédié, mise à jour du système, jonction au réseau Tailscale...) est automatisée par :

- [`src/config-ubuntu.sh`](../src/config-ubuntu.sh) — exécution manuelle directement sur la machine
- [`src/ansible/playbooks/config_ubuntu.yml`](../src/ansible/playbooks/config_ubuntu.yml) — exécution à distance depuis un poste de contrôle
