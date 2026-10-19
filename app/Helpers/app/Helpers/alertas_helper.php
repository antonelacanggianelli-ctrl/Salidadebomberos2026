<?php

/**
 * Envia una notificacion push a todos los suscriptos a traves de la API
 * de alertas (Vercel + Firebase).
 *
 * Requiere en el archivo .env de la raiz del proyecto:
 *     ALERTAS_API_KEY = la_clave_de_la_api
 *
 * Devuelve true si la API respondio 200, false en cualquier otro caso.
 * Nunca lanza excepciones: si algo falla, lo registra en el log de CodeIgniter.
 */
if (! function_exists('enviarPush')) {
    function enviarPush(string $titulo, string $mensaje): bool
    {
        $url   = 'https://api-alertas.vercel.app/api/enviar';
        $clave = (string) env('ALERTAS_API_KEY', '');

        if ($clave === '') {
            log_message('error', 'enviarPush: falta ALERTAS_API_KEY en el .env');

            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $clave,
            ],
            CURLOPT_POSTFIELDS     => json_encode(
                ['titulo' => $titulo, 'mensaje' => $mensaje],
                JSON_UNESCAPED_UNICODE
            ),
        ]);

        $respuesta = curl_exec($ch);
        $codigo    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error     = curl_error($ch);
        curl_close($ch);

        if ($codigo !== 200) {
            log_message('error', 'enviarPush: codigo ' . $codigo . ' ' . $error . ' ' . (string) $respuesta);

            return false;
        }

        return true;
    }
}