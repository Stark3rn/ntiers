# Repartition des tâches

## Configuration des services

| Catégorie            | Service        | Sous-domaine | Personne |
|:--------------------:|:--------------:|:------------:|:--------:|
| DNS Server           | Bind           | -            | Celestin |
| Entry Point          | Apache LB      | alb.*        | Alain    |
| Frontal cache        | Varnish        | vrn.*        | Alain    |
| Frontal cache        | Squid          | sqd.*        | Alain    |
| Static Web Server    | Apache         | nfs.*        | Lilian   |
| Core Web Application | Nginx LB       | nlb.*        | Lilian   |
| Core Web Application | PHP Web App    | web.*        | Lilian   |
| OIDC                 | Keycloak       | kclk.*       | Alain    |
| OIDC                 | Django captcha | captcha.*    | Alain    |
| Storage              | HA Proxy       | hap.*        | Lilian   |
| Storage              | Redis          | rd.*         | Lilian   |
| Storage              | MongoDB        | mdb.*        | Lilian   |
| Mail Service         | Postfix        | post.*       | Alain    |
| Mail Service         | MailHog        | mhg.*        | Alain    |
| ELK Stack            | Logstash       | log.*        | Celestin |
| ELK Stack            | Elasticsearch  | elastic.*    | Celestin |
| ELK Stack            | Kibana         | kiba.*       | Celestin |
| Sécurité             | Trivy          | trivy.*      | Celestin |
| Sécurité             | Wazuh          | wzh.*        | Celestin |
| Sécurité             | StepCA         | -            | Celestin |
| Monitoring           | Uptime Kuma    | up.*         | Lilian   |
