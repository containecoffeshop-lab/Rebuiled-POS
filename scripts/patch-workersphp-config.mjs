import { readFile, writeFile } from 'node:fs/promises';

const configPath = process.argv[2];

if (!configPath) {
    throw new Error('Usage: node scripts/patch-workersphp-config.mjs <generated-wrangler.jsonc>');
}

const source = await readFile(configPath, 'utf8');
const config = JSON.parse(source);

if (!config.limits || !Object.hasOwn(config.limits, 'cpu_ms')) {
    throw new Error('Unexpected Workers PHP Wrangler config; expected limits.cpu_ms.');
}

delete config.limits.cpu_ms;

if (Object.keys(config.limits).length === 0) {
    delete config.limits;
}

await writeFile(configPath, `${JSON.stringify(config, null, 2)}\n`);
