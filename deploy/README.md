# SecureIT Deployment Notes

This folder now holds deployment examples and environment-specific tenant config templates for the Docker-based SecureIT runtime.

## What belongs here

- `config.tenants.example.json`
- `config.tenants.json` for a local or host-specific deployment copy
- other deployment-time examples that are not part of the app image

## What does not belong here

- shared-host app bundles
- alternate local runtimes
- files that duplicate the Docker image contents

## Current deployment model

SecureIT should be built as a Docker image from this repository and run with mounted runtime data.

The app runtime expects:
- `app/` for the containerized web application
- `shared/` for reusable helper code
- `data/` for mounted tenant and report state
