import { createServer } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.dirname(fileURLToPath(import.meta.url));
const port = Number(process.env.PORT || 3000);

const mimeTypes = {
  '.css': 'text/css; charset=utf-8',
  '.gif': 'image/gif',
  '.html': 'text/html; charset=utf-8',
  '.ico': 'image/x-icon',
  '.jpeg': 'image/jpeg',
  '.jpg': 'image/jpeg',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.webp': 'image/webp',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2'
};

function safePath(urlPath) {
  const decoded = decodeURIComponent(urlPath.split('?')[0]);
  const relative = decoded === '/' ? 'firebase-app/index.html' : decoded.replace(/^\/+/, '');
  const absolute = path.resolve(root, relative);
  if (absolute !== root && !absolute.startsWith(`${root}${path.sep}`)) return null;
  return absolute;
}

async function resolveRequest(urlPath) {
  const requested = safePath(urlPath);
  if (!requested) return null;
  try {
    const details = await stat(requested);
    if (details.isFile()) return requested;
  } catch {
    // Fall through to the SPA entry point for client-side hash routes.
  }

  if (!path.extname(requested)) return path.join(root, 'firebase-app', 'index.html');
  return null;
}

const server = createServer(async (request, response) => {
  try {
    const filePath = await resolveRequest(request.url || '/');
    if (!filePath) {
      response.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      response.end('Not found');
      return;
    }
    const contents = await readFile(filePath);
    const extension = path.extname(filePath).toLowerCase();
    const isDevAsset = extension === '.js' || extension === '.mjs' || extension === '.css';
    response.writeHead(200, {
      'Cache-Control': filePath.endsWith('index.html') || isDevAsset ? 'no-cache' : 'public, max-age=86400, immutable',
      'Content-Type': mimeTypes[extension] || 'application/octet-stream'
    });
    response.end(contents);
  } catch (error) {
    response.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end(`Server error: ${error.message}`);
  }
});

server.listen(port, '127.0.0.1', () => {
  console.log(`Paradise Resort is running at http://localhost:${port}`);
});
