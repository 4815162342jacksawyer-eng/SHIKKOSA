# PLRA Theme Child: Git + WordPress Auto Deploy

## 1) Init repository locally

```bash
cd /home/sorrroka/files/plra-theme-child
git init
git add .
git commit -m "Initial commit: PLRA child theme"
git branch -M main
git remote add origin git@github.com:YOUR_USER/plra-theme-child.git
git push -u origin main
```

## 2) Configure WordPress plugin

Install and activate `Deployer for Git` on your WordPress site, then:

1. Add this GitHub repository.
2. Set branch to `main`.
3. Copy the deployment webhook URL from plugin settings.

## 3) Configure GitHub secret

In GitHub repository settings:

1. Open `Settings -> Secrets and variables -> Actions`.
2. Add secret `WP_DEPLOY_URL`.
3. Paste deployment webhook URL from WordPress plugin.

## 4) Deploy flow

Each push to `main` triggers `.github/workflows/deploy.yml`, which calls WordPress webhook.

```bash
git add .
git commit -m "Theme update"
git push
```

## Notes

- Edit theme files only locally (VS Code), not in WP Theme Editor.
- If site uses cache, clear it after deploy if changes are not visible.
