<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\AuditService;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    /**
     * Lista os pacientes do tenant do usuário logado (filtro por tenant já
     * aplicado pelo global scope da trait HasTenant). O cast
     * EncryptedWithDek descriptografa cpf/cns automaticamente ao serializar
     * para JSON.
     */
    public function index(Request $request)
    {
        $patients = Patient::query()->get();

        foreach ($patients as $patient) {
            AuditService::log('accessed', $patient);
        }

        return response()->json($patients);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'cpf' => 'required|string',
            'cns' => 'nullable|string',
        ]);

        $patient = Patient::create($data);

        return response()->json($patient, 201);
    }

    public function show(string $id)
    {
        $patient = Patient::findOrFail($id);

        AuditService::log('accessed', $patient);

        return response()->json($patient);
    }

    /**
     * Localiza um paciente pelo CPF sem descriptografar toda a base
     * (busca via blind index / token).
     */
    public function findByCpf(Request $request, string $cpf)
    {
        $patient = Patient::findByCpf($cpf);

        if (! $patient) {
            return response()->json(['message' => 'Paciente não encontrado.'], 404);
        }

        AuditService::log('accessed', $patient);

        return response()->json($patient);
    }
}
