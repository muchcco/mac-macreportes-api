<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;   // 👈 nuevo
use PhpOffice\PhpSpreadsheet\Style\Fill;     // 👈 nuevo

class DetalleAtencionesExport implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    /**
     * @var array
     */
    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function collection()
    {
        return collect($this->rows)->map(function ($row) {
            $r = (array) $row;

            return [
                $r['IDENTIFICADOR UNICO'] ?? null,
                $r['NOMBRE MAC'] ?? null,
                $r['NOMBRE ENTIDAD'] ?? null,
                $r['SERVICIO'] ?? null,
                $r['HORA DE LLEGADA'] ?? null,
                $r['HORA DE LLAMADO'] ?? null,
                $r['TIEMPO ESPERA'] ?? null,
                $r['HORA INICIO'] ?? null,
                $r['TIEMPO PROMEDIO'] ?? null,
                $r['FIN DE ATENCION'] ?? null,
                $r['TIEMPO TOTAL'] ?? null,
                $r['N° DE MODULO'] ?? null,
                $r['NUMERO DE TICKET'] ?? null,
                $r['ESTADO ATENCION'] ?? null,
                $r['FECHA'] ?? null,
                $r['MODALIDAD'] ?? null,
                $r['TIPO DE ATENCION'] ?? null,
                $r['N° DE DOCUMENTO'] ?? null,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'IDENTIFICADOR UNICO',
            'NOMBRE MAC',
            'NOMBRE ENTIDAD',
            'SERVICIO',
            'HORA DE LLEGADA',
            'HORA DE LLAMADO',
            'TIEMPO ESPERA',
            'HORA INICIO',
            'TIEMPO PROMEDIO',
            'FIN DE ATENCION',
            'TIEMPO TOTAL',
            'N° DE MODULO',
            'NUMERO DE TICKET',
            'ESTADO ATENCION',
            'FECHA',
            'MODALIDAD',
            'TIPO DE ATENCION',
            'N° DE DOCUMENTO',
        ];
    }

    public function title(): string
    {
        return 'DATA'; // nombre de la hoja
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet      = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn(); // ej. "R"
                $lastRow    = $sheet->getHighestRow();    // última fila con datos

                // 1) Filtros en encabezados (ya lo teníamos)
                $sheet->setAutoFilter('A1:' . $lastColumn . '1');

                // 2) Autoajustar ancho de columnas
                foreach (range('A', $lastColumn) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 3) Estilo del encabezado (fila 1)
                $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFE5E5E5'], // gris claro
                    ],
                    'borders' => [
                        'bottom' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // 4) Bordes para toda la tabla (para que se vean bien separados los grids)
                $sheet->getStyle('A1:' . $lastColumn . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);
            },
        ];
    }
}
