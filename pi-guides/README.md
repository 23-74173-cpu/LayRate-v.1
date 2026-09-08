# LayRate Pi Guides

Guides for any agentic AI (or human) managing the LayRate Raspberry Pi.

## Quick Start

```bash
ssh layratepi@LayRatePI.local
```

## Guide Index

| # | Guide | When to use |
|---|-------|-------------|
| 1 | [SSH Access & Connectivity](01-ssh-access.md) | Connecting to the Pi, mDNS issues, Windows compatibility |
| 2 | [Pi Configuration & WiFi](02-pi-configuration.md) | Changing WiFi, hotspot settings, network interfaces |
| 3 | [GitHub Runner Setup](03-github-runner.md) | Runner stuck in queue, re-registration, fresh install |
| 4 | [Pi Backup & Recovery](04-backup-recovery.md) | Backing up configs, restoring after SD card failure |
| 5 | [SD Card Direct Access](05-sd-card-access.md) | Accessing files when Pi is offline, SD card in USB adapter |
| 6 | [Quick Reference](06-quick-reference.md) | All paths, services, commands in one page |

## Pi Summary

- **Model:** Raspberry Pi 5
- **OS:** Raspberry Pi OS Lite 64-bit (Bookworm, Debian 12)
- **Website:** `/var/www/layrate/public/` (served by nginx)
- **GitHub Repo:** `23-74173-cpu/LayRate-v.1`
- **Runner:** Self-hosted GitHub Actions runner with 4-layer self-healing
- **Hotspot:** SSID `LayRatePI`, password `layratepi123`
