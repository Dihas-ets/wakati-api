<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * GET /api/exports/attendances.csv?from=&to=
     * Sans from/to : exporte TOUS les pointages (pas de restriction par
     * défaut au mois en cours, pour éviter tout risque de fichier vide
     * si les données de test ne tombent pas exactement dans le mois).
     */
    public function attendancesCsv(Request $request): StreamedResponse
    {
        $query = Attendance::with('user:id,name,email')->orderBy('date');

        if ($request->filled('from')) {
            $query->where('date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->where('date', '<=', $request->query('to'));
        }

        $rows = $query->get();

        $from = $request->query('from', 'debut');
        $to = $request->query('to', 'fin');
        $filename = "presences_{$from}_a_{$to}.csv";

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour un affichage correct des accents dans Excel.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Employé', 'Email', 'Date', 'Heure de pointage', 'Statut', 'Origine']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->user->name ?? '',
                    $row->user->email ?? '',
                    $row->date->toDateString(),
                    $row->check_in_time,
                    $row->status === 'late' ? 'En retard' : 'À l\'heure',
                    $row->source === 'offline_sync' ? 'Hors-ligne' : ($row->source === 'auto' ? 'Automatique' : 'En ligne'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}