import { readFile, writeFile } from 'node:fs/promises';

const workerPath = process.argv[2];

if (!workerPath) {
    throw new Error('Usage: node scripts/patch-workersphp-env.mjs <generated-worker.mjs>');
}

const source = await readFile(workerPath, 'utf8');
const envVarsPattern = /envVars: \(\) => \((\{[^\r\n]*\})\),/;
const configOverridesPattern = /configOverrides: \(\) => \((\{[^\r\n]*\})\),/;
const envVarsMatch = source.match(envVarsPattern);
const configOverridesMatch = source.match(configOverridesPattern);

if (!envVarsMatch || !configOverridesMatch) {
    throw new Error('Unexpected Workers PHP output; refusing to patch its runtime environment.');
}

const withSetupEnv = source.replace(
    envVarsPattern,
    'envVars: (env) => ({ ...$1, ...(env.INITIAL_SETUP_TOKEN ? { INITIAL_SETUP_TOKEN: env.INITIAL_SETUP_TOKEN } : {}) }),',
);
const patched = withSetupEnv.replace(
    configOverridesPattern,
    "configOverrides: (env) => ({ ...$1, ...(env.INITIAL_SETUP_TOKEN ? { 'app.initial_setup_token': env.INITIAL_SETUP_TOKEN } : {}) }),",
);

await writeFile(workerPath, patched);
