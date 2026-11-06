# Cloudflare Workers staging

The staging deployment runs Laravel with the community Workers PHP runtime and D1. It is not an official Cloudflare or Laravel integration.

## Required GitHub secrets

Add these under the repository's **Settings → Secrets and variables → Actions**:

- `CLOUDFLARE_API_TOKEN`: a Cloudflare API token with permission to deploy Workers and manage D1.
- `CLOUDFLARE_ACCOUNT_ID`: the Cloudflare account ID used by Wrangler.
- `APP_KEY`: a persistent Laravel encryption key in `base64:` format. Store it as a GitHub Actions secret; never commit it. The deployment uploads it to the Worker secret store, keeping it unchanged across deployments.
- `INITIAL_SETUP_TOKEN`: a strong, random code that will authorize creation of the first manager account. Do not commit it or send it in chat.

The workflow is manual: push the workflow to the repository, then run **Deploy POS staging to Cloudflare** from the Actions tab. It deploys to `rebuiled-pos-staging` and uses the `containecoffe` D1 database. The workflow initializes only an empty database or verifies that an existing database matches this application's migration history; it refuses a mismatched schema.

Open `/admin/pengaturan-awal` on the deployed site and enter the setup code to create the first manager. After setup, the wizard is closed. Remove the `INITIAL_SETUP_TOKEN` Worker secret after the manager account is created.

## Staging-only limitation

Do not use this deployment for real sales, inventory, or employee operations yet. D1 does not provide interactive PDO transactions, while core POS and stock workflows depend on `DB::transaction`; the community adapter therefore cannot guarantee those multi-step writes are atomic. Keep this database for disposable staging tests until transaction behavior has been replaced and verified for D1.

The initial D1 schema is generated from a fresh temporary SQLite database using Laravel migrations. No local application database or customer data is exported. Future migrations must be reviewed and applied to D1 before deploying application changes.
