# GASCON Ltd — static site mirror

Plain HTML snapshot of [gasconltd.com](https://gasconltd.com/), suitable for **Azure Static Web Apps** and GitHub Actions.

## Refresh the HTML

```bash
python3 build_static.py
```

Requires Python 3 with network access.

## GitHub CLI (`gh`) and Actions workflows

Pushing changes under `.github/workflows/` requires a token with the **`workflow`** scope. If `git push` is rejected for that reason, run:

```bash
gh auth refresh -h github.com -s workflow
```

Complete the browser/device prompt, then `git push` again.

Use **HTTPS** remotes with `gh auth setup-git` so pushes use your logged-in `gh` account (SSH may use a different GitHub user’s key).

## Azure Static Web Apps

1. In [Azure Portal](https://portal.azure.com), create a **Static Web App** and connect this GitHub repository (or add the deployment token manually).
2. In the GitHub repo: **Settings → Secrets and variables → Actions**, add **`AZURE_STATIC_WEB_APPS_API_TOKEN`** with the value from Azure: **Static Web App → Overview → Manage deployment token**.
3. Push to **`main`**; the workflow in `.github/workflows/azure-static-web-apps.yml` deploys the repo root (`index.html`, `staticwebapp.config.json`) with no build step.

Custom domain and HTTPS are configured in the Azure Static Web App blade.

## Local preview

```bash
python3 -m http.server 8080 --bind 127.0.0.1
```

Then open `http://127.0.0.1:8080/`.
