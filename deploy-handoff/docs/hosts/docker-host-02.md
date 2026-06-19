# docker-host-02

## Summary
- Hostname: `docker-host-02`
- Address: `192.168.36.40`
- Runtime: Docker Engine 29.0.4 running inside Proxmox VM `9203`
- Hypervisor: `proxmox-01` (`192.168.36.49`)
- Control plane: Portainer CE 2.21.0 on `https://192.168.36.40:9443`

## Observed Workloads
- `portainer`
- `postiz` and supporting `postgres` and `redis` containers
- `temporal` and supporting services
- `spotlight`
- `MCPFlow`, `phpMyAdmin`, `mariadb`, `mailpit`, and `docuseal`
- `open-brain`, `open-brain-db`, `open-brain-prometheus`, and `open-brain-grafana`
- `metabase`
- `paperclip`, `paperclip-db`, `serpbear`, `umami`, and `umami-db`
- `unifi-toolkit`
- `uptime-kuma`

## Observed Compose Projects
- `postiz-app`
- `mcpflow`
- `docker` (`open-brain/open-brain-stack.yml`)
- `paperclip-serpbear-umami`
- `unifi-toolkit`
- `uptime-kuma`
- unmanaged standalone containers may also exist

## Published and planned endpoints
- Metabase local origin: `http://192.168.36.40:3000`
- Metabase public hostname: `metabase.ict365.ky`
- SecureIT local origin: `http://192.168.36.40:8089`
- Paperclip local origin: `http://192.168.36.40:3100`
- Serpbear local origin: `http://192.168.36.40:3200`
- Umami local origin: `http://192.168.36.40:3300`
- Uptime Kuma local origin: `http://192.168.36.40:3001`
- UniFi Toolkit local origin: `http://192.168.36.40:8100`
- Uptime Kuma alert webhook target: AdamAI `192.168.36.30:5055` via `uptime-adamai.ict365.ky`
- Planned public hostnames: `paperclip.ict365.ky`, `secureit.ict365.ky`, `serpbear.ict365.ky`, and `umami.ict365.ky`

## Notes
- The `adam` account has `sudo` access but is not in the Docker group.
- This host is not bare metal; it currently runs as VM `9203` on `proxmox-01`.
- A stopped standby copy of this host currently exists as VM `9204` on `proxmox-02`.
- Native Proxmox replication is now active for this VM as job `9203-0` from `proxmox-01` to `proxmox-02` with schedule `*/2:00`.
- A nightly Proxmox backup job for this VM is now configured on `proxmox-01` to write snapshot backups to `NAS-BKPS` at `22:00` local time with `zstd` compression.
- Docker deployments on this host should use tracked stack or compose definitions rather than ad hoc `docker run` commands.
- Production changes on this host should record the approval point, maintenance-window decision, env-file validation, and monitoring outcome in the deployment summary.
- The full observed runtime estate for this host is cataloged in `infra/terraform/envs/prod` and `ansible/inventories/prod/host_vars/docker-host-02.yml`.
- Tracked runtime definitions now exist for `portainer`, `postiz-app`, `mcpflow`, `open-brain`, `metabase`, `paperclip-serpbear-umami`, `unifi-toolkit`, and `uptime-kuma`.
