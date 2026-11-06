import { readFile, writeFile } from 'node:fs/promises';

const workerPath = process.argv[2];

if (!workerPath) {
    throw new Error('Usage: node scripts/patch-workersphp-env.mjs <generated-worker.mjs>');
}

const source = await readFile(workerPath, 'utf8');
const envVarsPattern = /envVars: \(\) => \((\{[^\r\n]*\})\),/;
const match = source.match(envVarsPattern);

if (!match) {
    throw new Error('Unexpected Workers PHP output; refusing to alter the generated worker.');
}

const patched = source.replace(
    envVarsPattern,
    'envVars: (env) => ({ ...$1, ...(env.INITIAL_SETUP_TOKEN ? { INITIAL_SETUP_TOKEN: env.INITIAL_SETUP_TOKEN } : {}) }),',
);

await writeFile(workerPath, patched);
