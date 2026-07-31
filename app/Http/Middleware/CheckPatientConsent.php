<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Patient;

class CheckPatientConsent
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $patientId = $request->route('patient') ?? $request->route('id');

        if ($patientId) {
            $patient = Patient::find($patientId);
            
            if ($patient) {
                $validConsent = $patient->validConsent;
                
                if (!$validConsent || !$validConsent->isValid()) {
                    return response()->json([
                        'message' => 'Acesso negado: O paciente não possui um consentimento LGPD válido ativo.',
                        'error' => 'missing_lgpd_consent'
                    ], 403);
                }
            }
        }
        
        // Também verifica se a rota usa cpf
        $cpf = $request->route('cpf');
        if ($cpf) {
            $patient = Patient::findByCpf($cpf);
            if ($patient) {
                $validConsent = $patient->validConsent;
                if (!$validConsent || !$validConsent->isValid()) {
                    return response()->json([
                        'message' => 'Acesso negado: O paciente não possui um consentimento LGPD válido ativo.',
                        'error' => 'missing_lgpd_consent'
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
