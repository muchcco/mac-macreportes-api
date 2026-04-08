<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TiempoAtencionController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'ide_ent' => ['nullable', 'integer', 'min:1'],
            'entidad' => ['nullable', 'string', 'max:255'],
            'id_servicio_novo' => ['nullable', 'integer', 'min:1'],
            'servicio' => ['nullable', 'string', 'max:255'],
            'anio_ans' => ['nullable', 'integer', 'min:2000', 'max:9999'],
            'estado' => ['nullable', 'integer', 'in:0,1'],
        ]);

        $query = $this->baseQuery();

        if (isset($validated['ide_ent'])) {
            $query->where('pe.ide_ent', $validated['ide_ent']);
        }

        if (!empty($validated['entidad'])) {
            $query->whereRaw('UPPER(LTRIM(RTRIM(pe.nom_ent))) LIKE UPPER(?)', ['%' . trim($validated['entidad']) . '%']);
        }

        if (isset($validated['id_servicio_novo'])) {
            $query->where('ta.id_servicio_novo', $validated['id_servicio_novo']);
        }

        if (!empty($validated['servicio'])) {
            $query->whereRaw('UPPER(LTRIM(RTRIM(ps.nom_ser))) LIKE UPPER(?)', ['%' . trim($validated['servicio']) . '%']);
        }

        if (isset($validated['anio_ans'])) {
            $query->whereRaw('RTRIM(ta.anio_ans) = ?', [(string) $validated['anio_ans']]);
        }

        if (isset($validated['estado'])) {
            $query->where('ta.estado', $validated['estado']);
        }

        $data = $query
            ->orderBy('pe.nom_ent')
            ->orderBy('ps.nom_ser')
            ->orderBy('ta.id')
            ->get()
            ->map(fn ($row) => $this->mapRow($row))
            ->values();

        return response()->json([
            'success' => true,
            'filters' => [
                'ide_ent' => $validated['ide_ent'] ?? null,
                'entidad' => $validated['entidad'] ?? null,
                'id_servicio_novo' => $validated['id_servicio_novo'] ?? null,
                'servicio' => $validated['servicio'] ?? null,
                'anio_ans' => $validated['anio_ans'] ?? null,
                'estado' => $validated['estado'] ?? null,
            ],
            'total' => $data->count(),
            'data' => $data,
        ]);
    }

    public function show(int $id)
    {
        $row = $this->baseQuery()
            ->where('ta.id', $id)
            ->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Tiempo de atención no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->mapRow($row),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_servicio_novo' => ['required', 'integer', 'min:1'],
            't_espera' => ['required', 'integer', 'min:0', 'max:999'],
            't_atencion' => ['required', 'integer', 'min:0', 'max:999'],
            'anio_ans' => ['required', 'integer', 'min:2000', 'max:9999'],
            'estado' => ['nullable', 'integer', 'in:0,1'],
        ]);

        $service = $this->findService($validated['id_servicio_novo']);

        if (!$service) {
            throw ValidationException::withMessages([
                'id_servicio_novo' => 'El servicio seleccionado no existe.',
            ]);
        }

        $this->ensureUniqueServiceYear($validated['id_servicio_novo'], (string) $validated['anio_ans']);

        $id = DB::table('dbo.tiempos_ans')->insertGetId([
            'id_servicio_novo' => $validated['id_servicio_novo'],
            't_espera' => $validated['t_espera'],
            't_atencion' => $validated['t_atencion'],
            'anio_ans' => (string) $validated['anio_ans'],
            'fec_registro' => now(),
            'estado' => $validated['estado'] ?? 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tiempo de atención creado correctamente.',
            'data' => $this->getByIdOrFail($id),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'id_servicio_novo' => ['required', 'integer', 'min:1'],
            't_espera' => ['required', 'integer', 'min:0', 'max:999'],
            't_atencion' => ['required', 'integer', 'min:0', 'max:999'],
            'anio_ans' => ['required', 'integer', 'min:2000', 'max:9999'],
            'estado' => ['required', 'integer', 'in:0,1'],
        ]);

        $exists = DB::table('dbo.tiempos_ans')->where('id', $id)->exists();

        if (!$exists) {
            return response()->json([
                'success' => false,
                'message' => 'Tiempo de atención no encontrado.',
            ], 404);
        }

        $service = $this->findService($validated['id_servicio_novo']);

        if (!$service) {
            throw ValidationException::withMessages([
                'id_servicio_novo' => 'El servicio seleccionado no existe.',
            ]);
        }

        $this->ensureUniqueServiceYear(
            $validated['id_servicio_novo'],
            (string) $validated['anio_ans'],
            $id
        );

        DB::table('dbo.tiempos_ans')
            ->where('id', $id)
            ->update([
                'id_servicio_novo' => $validated['id_servicio_novo'],
                't_espera' => $validated['t_espera'],
                't_atencion' => $validated['t_atencion'],
                'anio_ans' => (string) $validated['anio_ans'],
                'estado' => $validated['estado'],
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Tiempo de atención actualizado correctamente.',
            'data' => $this->getByIdOrFail($id),
        ]);
    }

    public function destroy(int $id)
    {
        $affected = DB::table('dbo.tiempos_ans')
            ->where('id', $id)
            ->update([
                'estado' => 0,
            ]);

        if ($affected === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tiempo de atención no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tiempo de atención desactivado correctamente.',
            'data' => $this->getByIdOrFail($id),
        ]);
    }

    public function filtros()
    {
        $request = request();

        $validated = $request->validate([
            'anio_ans' => ['nullable', 'integer', 'min:2000', 'max:9999'],
            'solo_disponibles' => ['nullable', 'boolean'],
        ]);

        $soloDisponibles = filter_var($validated['solo_disponibles'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $anioAns = isset($validated['anio_ans']) ? (string) $validated['anio_ans'] : null;

        $entidades = DB::table('dbo.par_ent as pe')
            ->select([
                'pe.ide_ent',
                'pe.nom_ent',
            ])
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('dbo.par_ser as ps')
                    ->whereColumn('ps.ide_ent', 'pe.ide_ent')
                    ->whereExists(function ($subQuery) {
                        $subQuery->selectRaw('1')
                            ->from('dbo.tiempos_ans as ta')
                            ->whereColumn('ta.id_servicio_novo', 'ps.Ide_ser');
                    });
            })
            ->when($soloDisponibles && $anioAns, function ($query) use ($anioAns) {
                $query->whereExists(function ($subQuery) use ($anioAns) {
                    $subQuery->selectRaw('1')
                        ->from('dbo.par_ser as ps')
                        ->whereColumn('ps.ide_ent', 'pe.ide_ent')
                        ->whereNotExists(function ($innerQuery) use ($anioAns) {
                            $innerQuery->selectRaw('1')
                                ->from('dbo.tiempos_ans as ta')
                                ->whereColumn('ta.id_servicio_novo', 'ps.Ide_ser')
                                ->whereRaw('RTRIM(ta.anio_ans) = ?', [$anioAns]);
                        });
                });
            })
            ->orderBy('pe.nom_ent')
            ->get()
            ->map(function ($row) {
                return [
                    'ide_ent' => (int) $row->ide_ent,
                    'nom_ent' => $row->nom_ent,
                ];
            })
            ->values();

        $anios = DB::table('dbo.tiempos_ans')
            ->selectRaw('DISTINCT RTRIM(anio_ans) as anio_ans')
            ->orderByRaw('RTRIM(anio_ans) DESC')
            ->get()
            ->pluck('anio_ans')
            ->values();

        return response()->json([
            'success' => true,
            'filters' => [
                'anio_ans' => $validated['anio_ans'] ?? null,
                'solo_disponibles' => $soloDisponibles,
            ],
            'data' => [
                'entidades' => $entidades,
                'anios' => $anios,
                'estados' => [
                    ['value' => 1, 'label' => 'Activo'],
                    ['value' => 0, 'label' => 'Inactivo'],
                ],
            ],
        ]);
    }

    public function servicios(Request $request)
    {
        $validated = $request->validate([
            'ide_ent' => ['required', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:255'],
            'anio_ans' => ['nullable', 'integer', 'min:2000', 'max:9999'],
            'solo_disponibles' => ['nullable', 'boolean'],
        ]);

        $soloDisponibles = filter_var($validated['solo_disponibles'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = DB::table('dbo.par_ser as ps')
            ->join('dbo.par_ent as pe', 'pe.ide_ent', '=', 'ps.ide_ent')
            ->select([
                'ps.Ide_ser as ide_ser',
                'ps.ide_ent',
                'ps.nom_ser',
                'ps.nom_ent as servicio_nom_ent',
                'ps.nom_mac',
                'pe.nom_ent as entidad',
            ])
            ->where('ps.ide_ent', $validated['ide_ent']);

        if (!empty($validated['q'])) {
            $query->whereRaw('UPPER(LTRIM(RTRIM(ps.nom_ser))) LIKE UPPER(?)', ['%' . trim($validated['q']) . '%']);
        }

        if ($soloDisponibles && isset($validated['anio_ans'])) {
            $query->whereNotExists(function ($subQuery) use ($validated) {
                $subQuery->selectRaw('1')
                    ->from('dbo.tiempos_ans as ta')
                    ->whereColumn('ta.id_servicio_novo', 'ps.Ide_ser')
                    ->whereRaw('RTRIM(ta.anio_ans) = ?', [(string) $validated['anio_ans']]);
            });
        }

        $data = $query
            ->orderBy('ps.nom_ser')
            ->get()
            ->map(function ($row) {
                return [
                    'ide_ser' => (int) $row->ide_ser,
                    'ide_ent' => (int) $row->ide_ent,
                    'nom_ser' => $row->nom_ser,
                    'nom_ent' => $row->servicio_nom_ent,
                    'nom_mac' => $row->nom_mac,
                    'entidad' => $row->entidad,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'filters' => [
                'ide_ent' => $validated['ide_ent'],
                'q' => $validated['q'] ?? null,
                'anio_ans' => $validated['anio_ans'] ?? null,
                'solo_disponibles' => $soloDisponibles,
            ],
            'total' => $data->count(),
            'data' => $data,
        ]);
    }

    private function baseQuery(): Builder
    {
        return DB::table('dbo.tiempos_ans as ta')
            ->join('dbo.par_ser as ps', 'ps.Ide_ser', '=', 'ta.id_servicio_novo')
            ->join('dbo.par_ent as pe', 'pe.ide_ent', '=', 'ps.ide_ent')
            ->select([
                'ta.id',
                'ta.id_servicio_novo',
                'ta.t_espera',
                'ta.t_atencion',
                'ta.anio_ans',
                'ta.fec_registro',
                'ta.estado',
                'ps.Ide_ser as ide_ser',
                'ps.ide_ent',
                'ps.nom_ser',
                'ps.nom_ent as servicio_nom_ent',
                'ps.nom_mac',
                'pe.nom_ent as entidad',
            ]);
    }

    private function mapRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'id_servicio_novo' => (int) $row->id_servicio_novo,
            't_espera' => (int) $row->t_espera,
            't_atencion' => (int) $row->t_atencion,
            'anio_ans' => trim((string) $row->anio_ans),
            'fec_registro' => $row->fec_registro,
            'estado' => (int) $row->estado,
            'estado_label' => (int) $row->estado === 1 ? 'Activo' : 'Inactivo',
            'servicio' => [
                'ide_ser' => (int) $row->ide_ser,
                'ide_ent' => (int) $row->ide_ent,
                'nom_ser' => $row->nom_ser,
                'nom_ent' => $row->servicio_nom_ent,
                'nom_mac' => $row->nom_mac,
            ],
            'entidad' => [
                'ide_ent' => (int) $row->ide_ent,
                'nom_ent' => $row->entidad,
            ],
        ];
    }

    private function getByIdOrFail(int $id): array
    {
        $row = $this->baseQuery()
            ->where('ta.id', $id)
            ->first();

        if (!$row) {
            abort(404, 'Tiempo de atención no encontrado.');
        }

        return $this->mapRow($row);
    }

    private function findService(int $serviceId): ?object
    {
        return DB::table('dbo.par_ser')
            ->where('Ide_ser', $serviceId)
            ->first();
    }

    private function ensureUniqueServiceYear(int $serviceId, string $anioAns, ?int $ignoreId = null): void
    {
        $query = DB::table('dbo.tiempos_ans')
            ->where('id_servicio_novo', $serviceId)
            ->whereRaw('RTRIM(anio_ans) = ?', [trim($anioAns)]);

        if ($ignoreId !== null) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'id_servicio_novo' => 'Ya existe un tiempo de atención registrado para ese servicio en el año indicado.',
            ]);
        }
    }
}
