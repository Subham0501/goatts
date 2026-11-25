import { copyFileSync, existsSync } from 'node:fs'

const source = 'public/build/sw.js'
const target = 'public/sw.js'

if (!existsSync(source)) {
    console.error('Expected the built service worker at public/build/sw.js')
    process.exit(1)
}

copyFileSync(source, target)
console.log('Copied service worker to public/sw.js')
