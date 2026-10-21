<?php

namespace App\Controllers;

use Kreait\Firebase\Factory;

class Home extends BaseController
{
    public function index()
    {
        try {
            $firebase = (new Factory)
                ->withServiceAccount(APPPATH . 'Config/firebase-credentials.json');

            // Conectamos a Firestore
            $firestore = $firebase->createFirestore();
            $database = $firestore->database();
            
            // Intentamos guardar un documento de prueba en una colección llamada 'test_conexion'
            $documentReference = $database->collection('test_conexion')->document('prueba');
            $documentReference->set([
                'estado' => '¡Firestore conectado con éxito!',
                'fecha' => date('Y-m-d H:i:s')
            ]);

            return "¡Excelente! Firestore se conectó y guardó el documento correctamente.";
            
        } catch (\Exception $e) {
            return "Error al conectar con Firestore: " . $e->getMessage();
        }
    }
}