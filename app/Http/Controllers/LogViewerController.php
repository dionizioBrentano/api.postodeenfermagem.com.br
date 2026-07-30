<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogViewerController extends Controller
{
    public function index()
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!File::exists($logPath)) {
            return response()->json(['logs' => 'Nenhum log encontrado.']);
        }

        // Ler as últimas 500 linhas do arquivo de log para não sobrecarregar
        $lines = file($logPath);
        $lastLines = array_slice($lines, -500);
        
        return response()->json(['logs' => implode("", $lastLines)]);
    }
}
