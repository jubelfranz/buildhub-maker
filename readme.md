# 🛠️ BuildHub Maker: Quick Setup Guide

## 1. GitHub Preparation
1. **Create Repo:** Create a private repository (e.g., `plugin-name-pro`).
2. **Generate Token:** 
   - Settings > Developer Settings > Personal access tokens (classic).
   - Scopes: `repo` & `workflow`.
3. **Set Actions Secrets:**
   - Go to: Repo > Settings > Secrets and variables > Actions.
   - Add: `WPORG_USERNAME`, `WPORG_PASSWORD`, `MAIL_USERNAME`, `MAIL_PASSWORD`.

## 2. Plugin Installation
1. **Upload:** Place `/buildhub-maker/` in `/wp-content/plugins/`.
2. **Activate:** Activate in WP-Admin (Pages are auto-generated).
3. **Configure:** 
   - Copy `/templates/template-maker-config.php` to `/maker-config.php`.
   - Fill in `GH_TOKEN`, `GH_REPO`, and SMTP details.
4. **Secure:** Ensure `.htaccess` exists to block `maker-config.php`.

## 3. Deployment Workflow
1. **Analyze:** Upload your local Plugin ZIP to `/buildhub-workspace/`.
2. **Build:** Click **🚀 START BUILD**. Checks headers and creates FREE/PRO versions.
3. **Test:** Click **📧 SMTP Test** to verify GitHub-to-Email communication.
4. **Deploy:** 
   - **Freemius:** Uploads PRO ZIP via API.
   - **WP.org:** Triggers SVN upload and sends Review-Email + BCC.

## 4. Troubleshooting
- **Code 0:** Server Firewall blocks outgoing cURL. Contact Hoster.
- **Code 401:** GitHub Token invalid or missing scopes.
- **Code 404:** Repository path mismatch.
- **No Runs Yet:** Check if `.github/workflows/` exists in your Repo.
