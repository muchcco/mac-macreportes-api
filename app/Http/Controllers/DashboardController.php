<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function ubicaciones()
    {
        $rows = DB::table('Departamento as dep')
            ->join('Provincia as pro', 'pro.IdDepartamento', '=', 'dep.IdDepartamento')
            ->join('Distrito as dis', 'dis.IdProvincia', '=', 'pro.IdProvincia')
            ->select(
                'dep.IdDepartamento as id_departamento',
                'dep.Nombre as departamento',
                'dep.Ubigeo as ubigeo_departamento',
                'pro.IdProvincia as id_provincia',
                'pro.Nombre as provincia',
                'pro.Ubigeo as ubigeo_provincia',
                'dis.IdDistrito as id_distrito',
                'dis.Nombre as distrito',
                'dis.Ubigeo as ubigeo_distrito'
            )
            ->orderBy('dep.IdDepartamento')
            ->orderBy('pro.IdProvincia')
            ->orderBy('dis.IdDistrito')
            ->get();

        $tree = [];

        foreach ($rows as $row) {
            $depId = (int) $row->id_departamento;
            $proId = (int) $row->id_provincia;

            if (!isset($tree[$depId])) {
                $tree[$depId] = [
                    'id_departamento' => $depId,
                    'departamento' => $row->departamento,
                    'ubigeo_departamento' => $row->ubigeo_departamento,
                    'provincias' => [],
                ];
            }

            if (!isset($tree[$depId]['provincias'][$proId])) {
                $tree[$depId]['provincias'][$proId] = [
                    'id_provincia' => $proId,
                    'provincia' => $row->provincia,
                    'ubigeo_provincia' => $row->ubigeo_provincia,
                    'distritos' => [],
                ];
            }

            $tree[$depId]['provincias'][$proId]['distritos'][] = [
                'id_distrito' => (int) $row->id_distrito,
                'distrito' => $row->distrito,
                'ubigeo_distrito' => $row->ubigeo_distrito,
            ];
        }

        $data = array_values(array_map(function ($dep) {
            $dep['provincias'] = array_values($dep['provincias']);
            return $dep;
        }, $tree));

        return response()->json([
            'success' => true,
            'count_departamentos' => count($data),
            'data' => $data,
        ]);
    }

    public function mapa(Request $request)
    {
        $validated = $request->validate([
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'id_departamento' => ['nullable', 'integer', 'min:1'],
            'tip_aten' => ['nullable', 'string', 'max:100'],
            'tip_cnl' => ['nullable', 'string', 'max:50'],
            'nom_mac' => ['nullable', 'string', 'max:200'],
        ]);

        $kpiByDep = DB::table('KPI_DAS_ATE_GEN as k')
            ->selectRaw('
                TRY_CAST(k.Cod_Dep AS INT) as id_departamento,
                (
                    SUM(CASE
                        WHEN UPPER(LTRIM(RTRIM(k.Tip_Cnl))) = \'ALO MAC\' THEN ISNULL(k.sum_alo, 0)
                        ELSE 0
                    END)
                    + COUNT(DISTINCT CASE
                        WHEN UPPER(LTRIM(RTRIM(k.Tip_Cnl))) = \'CENTROS MAC\' THEN k.IDE_ATE
                        ELSE NULL
                    END)
                    + COUNT(DISTINCT CASE
                        WHEN UPPER(LTRIM(RTRIM(k.Tip_Cnl))) = \'MAC EXPRESS\' THEN k.IDE_ATE
                        ELSE NULL
                    END)
                ) as total_atenciones,
                SUM(CASE
                    WHEN UPPER(LTRIM(RTRIM(k.Tip_Cnl))) = \'ALO MAC\' THEN ISNULL(k.sum_alo, 0)
                    ELSE 0
                END) as total_alo_mac,
                COUNT(DISTINCT CASE
                    WHEN UPPER(LTRIM(RTRIM(k.Tip_Cnl))) = \'CENTROS MAC\' THEN k.IDE_ATE
                    ELSE NULL
                END) as total_centros_mac,
                COUNT(DISTINCT CASE
                    WHEN UPPER(LTRIM(RTRIM(k.Tip_Cnl))) = \'MAC EXPRESS\' THEN k.IDE_ATE
                    ELSE NULL
                END) as total_mac_express
            ');

        if (!empty($validated['fecha_inicio'])) {
            $kpiByDep->whereDate('k.Fec_ate', '>=', $validated['fecha_inicio']);
        }

        if (!empty($validated['fecha_fin'])) {
            $kpiByDep->whereDate('k.Fec_ate', '<=', $validated['fecha_fin']);
        }

        if (!empty($validated['tip_aten'])) {
            $kpiByDep->whereRaw('UPPER(LTRIM(RTRIM(k.tip_aten))) = UPPER(?)', [trim($validated['tip_aten'])]);
        }

        if (!empty($validated['tip_cnl'])) {
            $kpiByDep->whereRaw('UPPER(LTRIM(RTRIM(k.Tip_Cnl))) = UPPER(?)', [trim($validated['tip_cnl'])]);
        }

        if (!empty($validated['nom_mac'])) {
            $kpiByDep->whereRaw('UPPER(LTRIM(RTRIM(k.Nom_mac))) = UPPER(?)', [trim($validated['nom_mac'])]);
        }

        $kpiByDep->groupBy(DB::raw('TRY_CAST(k.Cod_Dep AS INT)'));

        $query = DB::table('Departamento as d')
            ->leftJoinSub($kpiByDep, 'kpi', function ($join) {
                $join->on('kpi.id_departamento', '=', 'd.IdDepartamento');
            })
            ->selectRaw("
                d.IdDepartamento as id_departamento,
                d.Nombre as departamento,
                d.Ubigeo as ubigeo_departamento,
                ISNULL(kpi.total_atenciones, 0) as total_atenciones,
                ISNULL(kpi.total_alo_mac, 0) as total_alo_mac,
                ISNULL(kpi.total_centros_mac, 0) as total_centros_mac,
                ISNULL(kpi.total_mac_express, 0) as total_mac_express
            ")
            ->orderBy('d.IdDepartamento');

        if (!empty($validated['id_departamento'])) {
            $query->where('d.IdDepartamento', $validated['id_departamento']);
        }

        if (!empty($validated['departamento'])) {
            $query->whereRaw('UPPER(d.Nombre) = UPPER(?)', [trim($validated['departamento'])]);
        }

        $data = $query->get()->map(function ($row) {
            return [
                'id_departamento' => (int) $row->id_departamento,
                'departamento' => $row->departamento,
                'ubigeo_departamento' => $row->ubigeo_departamento,
                'total_atenciones' => (int) $row->total_atenciones,
                'total_alo_mac' => (int) $row->total_alo_mac,
                'total_centros_mac' => (int) $row->total_centros_mac,
                'total_mac_express' => (int) $row->total_mac_express,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'filters' => [
                'fecha_inicio' => $validated['fecha_inicio'] ?? null,
                'fecha_fin' => $validated['fecha_fin'] ?? null,
                'id_departamento' => $validated['id_departamento'] ?? null,
                'departamento' => $validated['departamento'] ?? null,
                'tip_aten' => $validated['tip_aten'] ?? null,
                'tip_cnl' => $validated['tip_cnl'] ?? null,
                'nom_mac' => $validated['nom_mac'] ?? null,
            ],
            'summary' => [
                'total_departamentos' => $data->count(),
                'total_atenciones' => $data->sum('total_atenciones'),
                'total_alo_mac' => $data->sum('total_alo_mac'),
                'total_centros_mac' => $data->sum('total_centros_mac'),
                'total_mac_express' => $data->sum('total_mac_express'),
            ],
            'data' => $data,
        ]);
    }
}
