<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\AuditService;
use App\Http\Requests\PatientRequest;
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

    public function store(PatientRequest $request)
    {
        $data = $request->validated();

        $patient = Patient::create($data);

        return response()->json($patient, 201);
    }

    public function show(string $id)
    {
        $patient = Patient::findOrFail($id);

        AuditService::log('accessed', $patient);

        return response()->json($patient);
    }

    public function update(PatientRequest $request, string $id)
    {
        $patient = Patient::findOrFail($id);
        $data = $request->validated();

        $patient->update($data);

        AuditService::log('updated', $patient);

        return response()->json($patient);
    }

    public function destroy(string $id)
    {
        $patient = Patient::findOrFail($id);
        $patient->delete();

        AuditService::log('deleted', $patient);

        return response()->json(null, 204);
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
