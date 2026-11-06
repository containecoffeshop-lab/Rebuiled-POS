import { readFile, writeFile } from 'node:fs/promises';

const bakeScriptPath = process.argv[2];

if (!bakeScriptPath) {
    throw new Error('Usage: node scripts/patch-workersphp-bake.mjs <workersphp-bake.mjs>');
}

const source = await readFile(bakeScriptPath, 'utf8');
const dockerArgs = "\t'run', '--rm',\n";

if (source.split(dockerArgs).length !== 2) {
    throw new Error('Unexpected Workers PHP bake script; refusing to alter the Docker invocation.');
}

const patched = source.replace(
    dockerArgs,
    "\t'run', '--rm', '--user', `${process.getuid()}:${process.getgid()}`,\n",
);

await writeFile(bakeScriptPath, patched);
