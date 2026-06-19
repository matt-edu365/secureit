module "docker_host_02" {
  source = "../../modules/proxmox-vm"

  name           = "ict365-prod-docker-02"
  role           = "docker"
  node           = "unassigned"
  ipv4_addresses = ["192.168.36.40"]
  tags           = ["prod", "docker", "bootstrap"]
}

module "prod_lan" {
  source = "../../modules/network"

  name    = "ict365-prod-lan"
  cidr    = "192.168.36.0/24"
  gateway = "192.168.36.1"
}

module "proxmox_01_service" {
  source = "../../modules/service-catalog-entry"

  name              = "proxmox-01"
  category          = "virtualization-node"
  runtime_host      = "proxmox-01"
  management_status = "managed"
  source_of_truth   = "ansible/playbooks/proxmox-cluster.yml"
  endpoints         = ["https://192.168.36.49:8006"]
  notes = [
    "Hostname and HTTPS certificate now report proxmox-01.edu365.ky",
    "Member of proxmox-cluster"
  ]
}

module "proxmox_02_service" {
  source = "../../modules/service-catalog-entry"

  name              = "proxmox-02"
  category          = "virtualization-node"
  runtime_host      = "proxmox-02"
  management_status = "managed"
  source_of_truth   = "ansible/playbooks/proxmox-cluster.yml"
  endpoints         = ["https://192.168.36.48:8006"]
  notes = [
    "Hostname and HTTPS certificate now report proxmox-02.edu365.ky",
    "Member of proxmox-cluster"
  ]
}

module "proxmox_cluster_service" {
  source = "../../modules/service-catalog-entry"

  name              = "proxmox-cluster"
  category          = "virtualization-cluster"
  runtime_host      = "proxmox-control-plane"
  management_status = "import_required"
  source_of_truth   = "docs/runbooks/proxmox-cluster-bootstrap.md"
  endpoints         = ["https://192.168.36.49:8006", "https://192.168.36.48:8006"]
  notes = [
    "Cluster bootstrap completed on 2026-03-23",
    "Authenticated preflight on 2026-03-22 confirmed both nodes were on Proxmox VE 9.1.6",
    "Cluster is active and quorate with proxmox-01 and proxmox-02"
  ]
}

module "metabase_stack" {
  source = "../../modules/portainer-stack"

  name            = "metabase"
  environment     = "prod"
  runtime_host    = module.docker_host_02.vm.name
  definition_path = "docker/metabase/portainer-stack.yaml"
  published_routes = [
    "metabase.ict365.ky -> http://192.168.36.40:3000"
  ]
}

module "secureit_stack" {
  source = "../../modules/portainer-stack"

  name            = "secureit"
  environment     = "prod"
  runtime_host    = module.docker_host_02.vm.name
  definition_path = "docker/secureit/portainer-stack.yaml"
  published_routes = [
    "secureit.ict365.ky -> http://192.168.36.40:8089"
  ]
}

module "unifi_toolkit_stack" {
  source = "../../modules/portainer-stack"

  name            = "unifi-toolkit"
  environment     = "prod"
  runtime_host    = module.docker_host_02.vm.name
  definition_path = "docker/unifi-toolkit/portainer-stack.yaml"
  published_routes = [
    "internal-only -> http://192.168.36.40:8100"
  ]
}

module "paperclip_serpbear_umami_stack" {
  source = "../../modules/portainer-stack"

  name            = "paperclip-serpbear-umami"
  environment     = "prod"
  runtime_host    = module.docker_host_02.vm.name
  definition_path = "docker/paperclip-serpbear-umami/portainer-stack.yaml"
  published_routes = [
    "paperclip.ict365.ky -> http://192.168.36.40:3100",
    "serpbear.ict365.ky -> http://192.168.36.40:3200",
    "umami.ict365.ky -> http://192.168.36.40:3300"
  ]
}

module "portainer_service" {
  source = "../../modules/service-catalog-entry"

  name              = "portainer"
  category          = "control-plane"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/portainer/portainer-stack.yaml"
  endpoints         = ["https://192.168.36.40:9443"]
}

module "postiz_service" {
  source = "../../modules/service-catalog-entry"

  name              = "postiz-app"
  category          = "compose-project"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/postiz-app/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:4007"]
}

module "temporal_service" {
  source = "../../modules/service-catalog-entry"

  name              = "temporal"
  category          = "runtime-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/postiz-app/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:7233", "http://192.168.36.40:8088"]
}

module "spotlight_service" {
  source = "../../modules/service-catalog-entry"

  name              = "spotlight"
  category          = "runtime-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/postiz-app/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:8969"]
}

module "mcpflow_service" {
  source = "../../modules/service-catalog-entry"

  name              = "mcpflow"
  category          = "compose-project"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/mcpflow/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:8080"]
}

module "phpmyadmin_service" {
  source = "../../modules/service-catalog-entry"

  name              = "phpmyadmin"
  category          = "runtime-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/mcpflow/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:8081"]
}

module "mariadb_service" {
  source = "../../modules/service-catalog-entry"

  name              = "mariadb"
  category          = "runtime-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/mcpflow/portainer-stack.yaml"
  endpoints         = ["tcp://192.168.36.40:3306"]
}

module "mailpit_service" {
  source = "../../modules/service-catalog-entry"

  name              = "mailpit"
  category          = "runtime-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/mcpflow/portainer-stack.yaml"
  endpoints         = ["smtp://192.168.36.40:1025", "http://192.168.36.40:8025"]
}

module "docuseal_service" {
  source = "../../modules/service-catalog-entry"

  name              = "docuseal"
  category          = "runtime-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/mcpflow/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:3001"]
}

module "metabase_service" {
  source = "../../modules/service-catalog-entry"

  name              = "metabase"
  category          = "portainer-stack"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/metabase/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:3000", "https://metabase.ict365.ky"]
}

module "secureit_service" {
  source = "../../modules/service-catalog-entry"

  name              = "secureit"
  category          = "portainer-stack"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/secureit/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:8089", "https://secureit.ict365.ky"]
  notes = [
    "Container image is published from the SecureIT repo to GHCR as ghcr.io/matt-edu365/secureit:latest",
    "Host port 8089 avoids the existing Temporal UI binding on 8088"
  ]
}

module "unifi_toolkit_service" {
  source = "../../modules/service-catalog-entry"

  name              = "unifi-toolkit"
  category          = "compose-project"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/unifi-toolkit/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:8100"]
}

module "paperclip_service" {
  source = "../../modules/service-catalog-entry"

  name              = "paperclip"
  category          = "portainer-stack-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/paperclip-serpbear-umami/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:3100", "https://paperclip.ict365.ky"]
}

module "serpbear_service" {
  source = "../../modules/service-catalog-entry"

  name              = "serpbear"
  category          = "portainer-stack-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/paperclip-serpbear-umami/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:3200", "https://serpbear.ict365.ky"]
}

module "umami_service" {
  source = "../../modules/service-catalog-entry"

  name              = "umami"
  category          = "portainer-stack-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/paperclip-serpbear-umami/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:3300", "https://umami.ict365.ky"]
}

module "open_brain_service" {
  source = "../../modules/service-catalog-entry"

  name              = "open-brain"
  category          = "compose-project"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/open-brain/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:8200"]
}

module "open_brain_db_service" {
  source = "../../modules/service-catalog-entry"

  name              = "open-brain-db"
  category          = "runtime-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/open-brain/portainer-stack.yaml"
  endpoints         = ["tcp://192.168.36.40:6400"]
}

module "open_brain_prometheus_service" {
  source = "../../modules/service-catalog-entry"

  name              = "open-brain-prometheus"
  category          = "runtime-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/open-brain/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:9490"]
}

module "open_brain_grafana_service" {
  source = "../../modules/service-catalog-entry"

  name              = "open-brain-grafana"
  category          = "runtime-service"
  runtime_host      = module.docker_host_02.vm.name
  management_status = "managed"
  source_of_truth   = "docker/open-brain/portainer-stack.yaml"
  endpoints         = ["http://192.168.36.40:3400"]
}
