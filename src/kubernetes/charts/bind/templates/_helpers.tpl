{{- define "bind.fullname" -}}
{{- .Release.Name -}}
{{- end -}}

{{- define "bind.labels" -}}
app.kubernetes.io/name: {{ .Chart.Name }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/managed-by: Helm
{{- end -}}

{{- define "bind.selectorLabels" -}}
app.kubernetes.io/name: {{ .Chart.Name }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end -}}

{{/* Fichier de zone généré depuis .Values.zone */}}
{{- define "bind.zoneFile" -}}
$TTL {{ .Values.zone.ttl }}
@ IN SOA ns1.{{ .Values.zone.name }}. {{ .Values.zone.adminEmail }}. (
        {{ .Values.zone.serial | int64 }} ; serial (à incrémenter à chaque modification)
        3600       ; refresh
        600        ; retry
        604800     ; expire
        {{ .Values.zone.ttl }} )      ; negative TTL
    IN NS ns1.{{ .Values.zone.name }}.
{{ range .Values.zone.records }}
{{- if .comment }}
; {{ .comment }}
{{- end }}
{{- $name := .name }}
{{- $type := .type | default "A" }}
{{- range $value := (.values | default (list .value)) }}
{{ printf "%-10s IN %-6s %s" $name $type $value }}
{{- end }}
{{- end }}
{{- end -}}
