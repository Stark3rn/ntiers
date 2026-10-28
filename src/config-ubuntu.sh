set -euo pipefail

SSH_PORT="${SSH_PORT:-6767}"
SSHD_CONFIG="/etc/ssh/sshd_config"
TAILSCALE_AUTHKEY="${TAILSCALE_AUTHKEY:-}"

log() {
  printf '\n==> %s\n' "$*"
}

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Ce script doit être exécuté en root (sudo config-ubuntu.sh)." >&2
  exit 1
fi

log "Mise à jour du système"
apt update
apt upgrade -y

log "Installation d'OpenSSH server et du client NFS"
# nfs-common est requis par kubelet pour monter les volumes de type "nfs"
apt install -y openssh-server nfs-common

log "Configuration du port SSH (${SSH_PORT})"
if grep -qE '^[[:space:]]*#?[[:space:]]*Port[[:space:]]+[0-9]+' "$SSHD_CONFIG"; then
  sed -i -E "s/^[[:space:]]*#?[[:space:]]*Port[[:space:]]+[0-9]+/Port ${SSH_PORT}/" "$SSHD_CONFIG"
else
  printf '\nPort %s\n' "$SSH_PORT" >> "$SSHD_CONFIG"
fi
sshd -t -f "$SSHD_CONFIG"

log "Installation de Tailscale"
curl -fsSL https://tailscale.com/install.sh | sh

log "Connexion au réseau Tailscale"
if tailscale status >/dev/null 2>&1; then
  echo "Tailscale est déjà connecté sur cette machine, rien à faire."
elif [[ -n "$TAILSCALE_AUTHKEY" ]]; then
  tailscale up --authkey="$TAILSCALE_AUTHKEY" --ssh
else
  echo "Aucune TAILSCALE_AUTHKEY fournie : authentification interactive."
  echo "Ouvre le lien affiché ci-dessous pour connecter cette machine au tailnet."
  tailscale up --ssh
fi

# --- Redémarrage des services

log "Rechargement de la configuration systemd"
systemctl daemon-reload

log "Redémarrage du service SSH (nouveau port : ${SSH_PORT})"
systemctl restart ssh

log "Terminé."
echo "Nouvelle connexion : ssh -p ${SSH_PORT} <user>@<ip-tailscale-ou-lan>"
