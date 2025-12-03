{{/*
Expand the name of the chart.
*/}}
{{- define "microservices.name" -}}
{{- default .Chart.Name .Values.nameOverride | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Create a default fully qualified app name.
*/}}
{{- define "microservices.fullname" -}}
{{- if .Values.fullnameOverride }}
{{- .Values.fullnameOverride | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- $name := default .Chart.Name .Values.nameOverride }}
{{- if contains $name .Release.Name }}
{{- .Release.Name | trunc 63 | trimSuffix "-" }}
{{- else }}
{{- printf "%s-%s" .Release.Name $name | trunc 63 | trimSuffix "-" }}
{{- end }}
{{- end }}
{{- end }}

{{/*
Create chart name and version as used by the chart label.
*/}}
{{- define "microservices.chart" -}}
{{- printf "%s-%s" .Chart.Name .Chart.Version | replace "+" "_" | trunc 63 | trimSuffix "-" }}
{{- end }}

{{/*
Common labels
*/}}
{{- define "microservices.labels" -}}
helm.sh/chart: {{ include "microservices.chart" . }}
{{ include "microservices.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Selector labels
*/}}
{{- define "microservices.selectorLabels" -}}
app.kubernetes.io/name: {{ include "microservices.name" . }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end }}

{{/*
Create the name of the service account to use
*/}}
{{- define "microservices.serviceAccountName" -}}
{{- if .Values.serviceAccount.create }}
{{- default (include "microservices.fullname" .) .Values.serviceAccount.name }}
{{- else }}
{{- default "default" .Values.serviceAccount.name }}
{{- end }}
{{- end }}

{{/*
Auth Service labels
*/}}
{{- define "authService.labels" -}}
helm.sh/chart: {{ include "microservices.chart" . }}
{{ include "authService.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Auth Service selector labels
*/}}
{{- define "authService.selectorLabels" -}}
app.kubernetes.io/name: {{ .Values.authService.name }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/component: auth-service
{{- end }}

{{/*
User Service labels
*/}}
{{- define "userService.labels" -}}
helm.sh/chart: {{ include "microservices.chart" . }}
{{ include "userService.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
User Service selector labels
*/}}
{{- define "userService.selectorLabels" -}}
app.kubernetes.io/name: {{ .Values.userService.name }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/component: user-service
{{- end }}

{{/*
User Consumer labels
*/}}
{{- define "userConsumer.labels" -}}
helm.sh/chart: {{ include "microservices.chart" . }}
{{ include "userConsumer.selectorLabels" . }}
{{- if .Chart.AppVersion }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
{{- end }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
User Consumer selector labels
*/}}
{{- define "userConsumer.selectorLabels" -}}
app.kubernetes.io/name: {{ .Values.userService.name }}-consumer
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/component: user-consumer
{{- end }}

{{/*
Postgres Auth labels
*/}}
{{- define "postgresAuth.labels" -}}
helm.sh/chart: {{ include "microservices.chart" . }}
{{ include "postgresAuth.selectorLabels" . }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Postgres Auth selector labels
*/}}
{{- define "postgresAuth.selectorLabels" -}}
app.kubernetes.io/name: {{ .Values.postgresAuth.name }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/component: database
{{- end }}

{{/*
Postgres User labels
*/}}
{{- define "postgresUser.labels" -}}
helm.sh/chart: {{ include "microservices.chart" . }}
{{ include "postgresUser.selectorLabels" . }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
Postgres User selector labels
*/}}
{{- define "postgresUser.selectorLabels" -}}
app.kubernetes.io/name: {{ .Values.postgresUser.name }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/component: database
{{- end }}

{{/*
RabbitMQ labels
*/}}
{{- define "rabbitmq.labels" -}}
helm.sh/chart: {{ include "microservices.chart" . }}
{{ include "rabbitmq.selectorLabels" . }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
{{- end }}

{{/*
RabbitMQ selector labels
*/}}
{{- define "rabbitmq.selectorLabels" -}}
app.kubernetes.io/name: {{ .Values.rabbitmq.name }}
app.kubernetes.io/instance: {{ .Release.Name }}
app.kubernetes.io/component: message-broker
{{- end }}

{{/*
Image registry
*/}}
{{- define "microservices.imageRegistry" -}}
{{- printf "%s/%s" .Values.global.imageRegistry .Values.global.imageOwner }}
{{- end }}
