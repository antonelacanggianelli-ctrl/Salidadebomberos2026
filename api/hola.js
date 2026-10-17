// Archivo: /api/hola.js
export default function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*'); // Evita problemas de CORS
  res.status(200).json({ mensaje: '¡Hola desde la API serverless de Vercel!' });
}