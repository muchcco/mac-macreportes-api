<?php

namespace App\Http\Controllers;

use App\Exports\DetalleAtencionesExport;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ReporteDetalleAtencionController extends Controller
{

    /**
     * Obtiene TODOS los datos (rows) + summary, ya sea desde cache o BD.
     */
    private function getFullReportData(
        string $fechaInicio,
        string $fechaFin,
        string $ideSerLista,
        string $nomMacLista,
        ?string $estado
    ): array {
        // 🔑 Clave de cache basada en los filtros
        $cacheKey = 'detalle_atenciones:' . md5(json_encode([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'ide_ser'      => $ideSerLista,
            'nom_mac'      => $nomMacLista,
            'estado'       => $estado,
        ]));

        // ⏱ Tiempo de vida del cache (ej. 1 día)
        $ttlSeconds = 60 * 60 * 24;

        return Cache::remember($cacheKey, $ttlSeconds, function () use (
            $fechaInicio,
            $fechaFin,
            $ideSerLista,
            $nomMacLista,
            $estado
        ) {
            // 1) Ejecutar SP
            $rows = DB::select(
                'EXEC dbo.USP_REP_DETALLE_ATENCIONES ?, ?, ?, ?, ?',
                [
                    $fechaInicio,
                    $fechaFin,
                    $ideSerLista,
                    $nomMacLista,
                    $estado,
                ]
            );

            // 2) Calcular summary (lo que ya haces en index)
            $total          = count($rows);
            $terminados     = 0;
            $noTerminados   = 0;
            $serviceCounts  = [];

            foreach ($rows as $row) {
                $estadoAtencion = '';
                if (property_exists($row, 'ESTADO ATENCION')) {
                    $estadoAtencion = strtolower(trim($row->{'ESTADO ATENCION'}));
                } elseif (property_exists($row, 'Est_ate')) {
                    $estadoAtencion = strtolower(trim($row->Est_ate));
                }

                if ($estadoAtencion === 'terminado') {
                    $terminados++;
                } elseif ($estadoAtencion !== '') {
                    $noTerminados++;
                }

                $servicio = property_exists($row, 'SERVICIO')
                    ? $row->SERVICIO
                    : (property_exists($row, 'Servicio') ? $row->Servicio : 'Sin servicio');

                if (!isset($serviceCounts[$servicio])) {
                    $serviceCounts[$servicio] = 0;
                }
                $serviceCounts[$servicio]++;
            }

            arsort($serviceCounts);

            $topN       = 6;
            $labels     = [];
            $values     = [];
            $otrosTotal = 0;
            $idx        = 0;

            foreach ($serviceCounts as $label => $count) {
                if ($idx < $topN) {
                    $labels[] = $label;
                    $values[] = $count;
                } else {
                    $otrosTotal += $count;
                }
                $idx++;
            }

            if ($otrosTotal > 0) {
                $labels[] = 'Otros';
                $values[] = $otrosTotal;
            }

            $serviceSummary = [];
            foreach ($labels as $i => $label) {
                $serviceSummary[] = [
                    'label' => $label,
                    'value' => $values[$i],
                ];
            }

            return [
                'rows'    => $rows,       // todos los registros
                'total'   => $total,
                'summary' => [
                    'estado' => [
                        'terminado'    => $terminados,
                        'no_terminado' => $noTerminados,
                    ],
                    'servicios' => $serviceSummary,
                ],
            ];
        });
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin'    => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'ide_ser'      => ['required', 'string'],
            'nom_mac'      => ['required', 'string'],
            'estado'       => ['nullable', 'string'],
            'page'         => ['nullable', 'integer', 'min:1'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $fechaInicio = $validated['fecha_inicio'];
        $fechaFin    = $validated['fecha_fin'];
        $ideSerLista = $validated['ide_ser'];
        $nomMacLista = $validated['nom_mac'];
        $estado      = $validated['estado'] ?? null;

        $page    = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 50;

        // 👉 Usamos el método cacheado
        $full = $this->getFullReportData(
            $fechaInicio,
            $fechaFin,
            $ideSerLista,
            $nomMacLista,
            $estado
        );

        $rows    = $full['rows'];
        $total   = $full['total'];
        $summary = $full['summary'];

        $offset = ($page - 1) * $perPage;

        if ($offset >= $total) {
            $items = [];
        } else {
            $items = array_slice($rows, $offset, $perPage);
        }

        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return response()->json([
            'success'   => true,
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'last_page' => $lastPage,
            'summary'   => $summary,     // 👈 ya calculado arriba
            'data'      => array_values($items),
        ]);
    }


    public function details(Request $request)
    {
        $servicios = DB::table('par_ent')
            ->select(
                DB::raw("CASE 
                    WHEN nom_ent LIKE '%RENIEC%' THEN 'RENIEC'
                    WHEN nom_ent LIKE '%MIGRACIONES%' THEN 'MIGRACIONES'
                    WHEN nom_ent LIKE '%RELACIONES EXTERIORES%' THEN 'RR.EE'
                    WHEN nom_ent LIKE '%ONP%' THEN 'ONP'
                    ELSE nom_ent
                END AS entidad"),
                DB::raw("STRING_AGG(CAST(ide_ent AS VARCHAR(10)), ', ') WITHIN GROUP (ORDER BY ide_ent) AS ids")
            )
            ->groupBy(DB::raw("CASE 
                WHEN nom_ent LIKE '%RENIEC%' THEN 'RENIEC'
                WHEN nom_ent LIKE '%MIGRACIONES%' THEN 'MIGRACIONES'
                WHEN nom_ent LIKE '%RELACIONES EXTERIORES%' THEN 'RR.EE'
                WHEN nom_ent LIKE '%ONP%' THEN 'ONP'
                ELSE nom_ent
            END"))
            ->orderBy('entidad')
            ->get()
            ->map(function ($item) {
                $item->ids_array = explode(', ', $item->ids);
                return $item;
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'detalle'   => 'Aquí van los detalles específicos solicitados.',
                'servicios' => $servicios,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin'    => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'ide_ser'      => ['required', 'string'],
            'nom_mac'      => ['required', 'string'],
            'estado'       => ['nullable', 'string'],
        ]);

        $fechaInicio = $validated['fecha_inicio'];
        $fechaFin    = $validated['fecha_fin'];
        $ideSerLista = $validated['ide_ser'];
        $nomMacLista = $validated['nom_mac'];
        $estado      = $validated['estado'] ?? null;

        // 👉 otra vez, usamos el método cacheado
        $full = $this->getFullReportData(
            $fechaInicio,
            $fechaFin,
            $ideSerLista,
            $nomMacLista,
            $estado
        );

        $rows = $full['rows'];

        $fileName = sprintf(
            'detalle_atenciones_%s_%s.xlsx',
            $fechaInicio,
            $fechaFin
        );

        return Excel::download(new DetalleAtencionesExport($rows), $fileName);
    }
}

