<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Border;
use PhpOffice\PhpWord\TemplateProcessor;

class MeetingDocxGeneratorService
{
    /**
     * Chemin vers le modèle Word institutionnel de référence (Style Dihas Group).
     */
    protected string $templatePath;

    public function __construct()
    {
        $this->templatePath = storage_path('app/templates/meeting_template_dihas.docx');
        Settings::setOutputEscapingEnabled(true);
    }

    /**
     * Génère la fiche de réunion Word (.docx) modifiable pour la Direction/DRH.
     */
    public function generate(Meeting $meeting): string
    {
        $this->ensureTemplateExists();

        $meeting->loadMissing([
            'redactor',
            'participants',
            'meetingParticipants.user',
            'topics.responsible',
            'kanbanTasks.assignedUser',
            'validations.user',
        ]);

        $template = new TemplateProcessor($this->templatePath);
        $stats = $meeting->attendance_stats;

        // --- 1. Injection des Variables Générales ---
        $companyName = CompanySetting::get('company_name', 'Dihas Group');
        $template->setValue('company_name', strtoupper($companyName));
        $template->setValue('meeting_title', $meeting->title ?? 'Réunion d\'Entreprise');
        $template->setValue('meeting_date', $meeting->date ? Carbon::parse($meeting->date)->translatedFormat('d F Y') : '—');
        $template->setValue('meeting_time', $meeting->time ? Carbon::parse($meeting->time)->format('H:i') : '—');
        $template->setValue('meeting_location', $meeting->location ?? 'Salle de réunion');
        $template->setValue('meeting_redactor', $meeting->redactor?->name ?? 'Direction / DRH');
        $template->setValue('meeting_status', strtoupper($meeting->status));
        $template->setValue('agenda', $meeting->agenda ?? 'Non spécifié');
        $template->setValue('summary_decisions', $meeting->summary_decisions ?: 'Aucune décision majeure enregistrée pour le moment.');
        $template->setValue('drh_notes', $meeting->drh_notes ?: 'Aucune note confidentielle DRH.');

        // --- 2. Statistiques d'Émargement / Effectifs ---
        $template->setValue('attendance_ratio', $stats['ratio_label'] ?? '0 / 0');
        $template->setValue('attendance_rate', ($stats['attendance_rate_percent'] ?? 0) . ' %');
        $template->setValue('total_invited', (string) ($stats['total_invited'] ?? 0));
        $template->setValue('total_present', (string) ($stats['total_present'] ?? 0));
        $template->setValue('total_absent', (string) ($stats['total_absent'] ?? 0));
        $template->setValue('total_excused', (string) ($stats['total_excused'] ?? 0));
        $template->setValue('generated_at', Carbon::now()->translatedFormat('d/m/Y H:i'));

        // --- 3. Remplissage Modulaire du Tableau des Participants ---
        $participantsData = [];
        $index = 1;
        foreach ($meeting->meetingParticipants as $mp) {
            $user = $mp->user;
            $statusLabel = match ($mp->attendance_status) {
                'present' => 'Présent',
                'absent' => 'Absent',
                'excused' => 'Excusé',
                default => 'Invité',
            };

            $checkinInfo = $mp->checked_in_at
                ? Carbon::parse($mp->checked_in_at)->format('H:i') . ' (' . ($mp->checkin_method ?? 'auto') . ')'
                : '—';

            $participantsData[] = [
                'part_num' => (string) $index++,
                'part_name' => $user?->name ?? 'Participant inconnu',
                'part_role' => $user?->job_title ?? ($user?->role ? ucfirst($user->role) : 'Collaborateur'),
                'part_status' => $statusLabel,
                'part_checkin' => $checkinInfo,
            ];
        }
        $this->populateTableRows($template, 'part_name', $participantsData, [
            'part_num' => '1',
            'part_name' => 'Aucun participant convoqué',
            'part_role' => '—',
            'part_status' => '—',
            'part_checkin' => '—',
        ]);

        // --- 4. Remplissage Modulaire du Tableau des Sujets & Évolutions ---
        $topicsData = [];
        $tIndex = 1;
        foreach ($meeting->topics as $topic) {
            $topicsData[] = [
                'topic_num' => (string) $tIndex++,
                'topic_subject' => $topic->subject ?? '—',
                'topic_situation' => $topic->current_situation ?? '—',
                'topic_decision' => $topic->evolution_decision ?? '—',
                'topic_resp' => $topic->responsible?->name ?? 'Non assigné',
                'topic_due' => $topic->due_date ? Carbon::parse($topic->due_date)->format('d/m/Y') : '—',
            ];
        }
        $this->populateTableRows($template, 'topic_subject', $topicsData, [
            'topic_num' => '1',
            'topic_subject' => 'Aucun point spécifique à l\'ordre du jour',
            'topic_situation' => '—',
            'topic_decision' => '—',
            'topic_resp' => '—',
            'topic_due' => '—',
        ]);

        // --- 5. Remplissage Modulaire du Tableau des Actions / Tâches Kanban ---
        $tasksData = [];
        $kIndex = 1;
        foreach ($meeting->kanbanTasks as $task) {
            $statusLabel = match ($task->status) {
                'todo' => 'À faire',
                'in_progress' => 'En cours',
                'review' => 'En révision',
                'done' => 'Terminé',
                default => $task->status,
            };

            $tasksData[] = [
                'task_num' => (string) $kIndex++,
                'task_title' => $task->title,
                'task_resp' => $task->assignedUser?->name ?? 'Non assigné',
                'task_due' => $task->due_date ? Carbon::parse($task->due_date)->format('d/m/Y') : '—',
                'task_status' => $statusLabel,
            ];
        }
        $this->populateTableRows($template, 'task_title', $tasksData, [
            'task_num' => '1',
            'task_title' => 'Aucune action Kanban requise',
            'task_resp' => '—',
            'task_due' => '—',
            'task_status' => '—',
        ]);

        // --- 6. Validations Institutionnelles (DRH & Direction) ---
        $drhVal = $meeting->validations->firstWhere('role_label', 'DRH');
        $dirVal = $meeting->validations->firstWhere('role_label', 'Direction');

        $template->setValue('val_drh_sign', $drhVal?->signed_at
            ? 'Validé le ' . Carbon::parse($drhVal->signed_at)->format('d/m/Y H:i') . ' par ' . ($drhVal->user?->name ?? 'DRH')
            : 'En attente de visa');
        $template->setValue('val_dir_sign', $dirVal?->signed_at
            ? 'Validé le ' . Carbon::parse($dirVal->signed_at)->format('d/m/Y H:i') . ' par ' . ($dirVal->user?->name ?? 'Direction Générale')
            : 'En attente de visa');

        // --- 7. Sauvegarde du Document Généré ---
        $outputDir = storage_path('app/meetings');
        if (! File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $fileName = sprintf('Compte_Rendu_Reunion_%d_%s.docx', $meeting->id, Carbon::now()->format('Ymd_His'));
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $fileName;

        $template->saveAs($outputPath);

        // Mettre à jour le chemin dans le modèle
        $meeting->update(['docx_file_path' => 'meetings/' . $fileName]);

        return $outputPath;
    }

    /**
     * Méthode générique et réutilisable pour le clonage et remplissage de tableaux dynamiques.
     * Cette méthode garantit une intégration propre sans erreur TemplateProcessor en cas de liste vide.
     *
     * @param TemplateProcessor $template L'instance TemplateProcessor
     * @param string $needlePlaceholder Le placeholder pivot d'une ligne du tableau (ex: 'part_name')
     * @param array $rows Liste des tableaux associatifs [ 'part_name' => '...', 'part_role' => '...' ]
     * @param array $fallbackRow Ligne de secours par défaut si la liste est vide
     */
    public function populateTableRows(
        TemplateProcessor $template,
        string $needlePlaceholder,
        array $rows,
        array $fallbackRow = []
    ): void {
        $count = count($rows);

        if ($count === 0) {
            // S'il n'y a pas de données, on crée 1 ligne avec les valeurs de fallback
            $template->cloneRow($needlePlaceholder, 1);
            foreach ($fallbackRow as $key => $val) {
                $template->setValue("{$key}#1", (string) $val);
            }
            return;
        }

        // On clone la ligne N fois
        $template->cloneRow($needlePlaceholder, $count);

        // On injecte chaque champ indexé par son numéro de ligne (1-indexed)
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            foreach ($row as $key => $value) {
                $template->setValue("{$key}#{$rowNumber}", (string) ($value ?? '—'));
            }
        }
    }

    /**
     * Vérifie si le fichier de template existe et est valide.
     * S'il est absent ou corrompu, il est généré automatiquement avec le style institutionnel Dihas Group.
     */
    protected function ensureTemplateExists(): void
    {
        Settings::setOutputEscapingEnabled(true);

        if (File::exists($this->templatePath)) {
            // Vérifier si le template existant est un XML valide et contient le placeholder dynamique company_name
            $zip = new \ZipArchive();
            if ($zip->open($this->templatePath) === true) {
                $docXml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($docXml && str_contains($docXml, '${company_name}')) {
                    libxml_use_internal_errors(true);
                    $xml = simplexml_load_string($docXml);
                    if ($xml !== false) {
                        return; // Le modèle existant est parfaitement valide et dynamique
                    }
                }
            }
            // S'il est corrompu, obsolète ou statique, on le supprime pour le régénérer proprement
            File::delete($this->templatePath);
        }

        $templateDir = dirname($this->templatePath);
        if (! File::isDirectory($templateDir)) {
            File::makeDirectory($templateDir, 0755, true);
        }

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1200,
            'marginRight' => 1200,
        ]);

        // Style du titre
        $headerTable = $section->addTable([
            'borderColor' => '795FFC',
            'borderSize' => 6,
            'cellMargin' => 120,
            'alignment' => JcTable::CENTER,
        ]);
        $headerTable->addRow();
        $headerCell = $headerTable->addCell(9000, ['bgColor' => 'F4F2FB']);
        $headerCell->addText('${company_name} / WAKATI MONO-ENTREPRISE', ['bold' => true, 'color' => '795FFC', 'size' => 10], ['alignment' => Jc::CENTER]);
        $headerCell->addText('FICHE OFFICIELLE DE RÉUNION ET COMPTE-RENDU', ['bold' => true, 'color' => '1F2937', 'size' => 16], ['alignment' => Jc::CENTER]);
        $headerCell->addText('${meeting_title}', ['bold' => true, 'color' => '4B5563', 'size' => 13], ['alignment' => Jc::CENTER]);

        $section->addTextBreak(1);

        // Informations Générales (En-tête)
        $metaTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);
        $metaTable->addRow();
        $metaTable->addCell(2200, ['bgColor' => 'F9FAFB'])->addText('Date :', ['bold' => true]);
        $metaTable->addCell(2300)->addText('${meeting_date}');
        $metaTable->addCell(2200, ['bgColor' => 'F9FAFB'])->addText('Heure :', ['bold' => true]);
        $metaTable->addCell(2300)->addText('${meeting_time}');

        $metaTable->addRow();
        $metaTable->addCell(2200, ['bgColor' => 'F9FAFB'])->addText('Lieu :', ['bold' => true]);
        $metaTable->addCell(2300)->addText('${meeting_location}');
        $metaTable->addCell(2200, ['bgColor' => 'F9FAFB'])->addText('Rédacteur :', ['bold' => true]);
        $metaTable->addCell(2300)->addText('${meeting_redactor}');

        $metaTable->addRow();
        $metaTable->addCell(2200, ['bgColor' => 'F9FAFB'])->addText('Statut :', ['bold' => true]);
        $metaTable->addCell(2300)->addText('${meeting_status}');
        $metaTable->addCell(2200, ['bgColor' => 'F9FAFB'])->addText('Taux de Présence :', ['bold' => true]);
        $metaTable->addCell(2300)->addText('${attendance_ratio} (${attendance_rate})', ['bold' => true, 'color' => '059669']);

        $section->addTextBreak(1);

        // Section Ordre du jour
        $section->addText('1. ORDRE DU JOUR ET RAPPELS', ['bold' => true, 'size' => 12, 'color' => '795FFC']);
        $section->addText('${agenda}', ['italic' => false]);

        $section->addTextBreak(1);

        // Section Émargement & Participants
        $section->addText('2. ÉMARGEMENT ET EFFECTIFS (Inscrits : ${total_invited} | Présents : ${total_present} | Absents : ${total_absent} | Excusés : ${total_excused})', [
            'bold' => true, 'size' => 12, 'color' => '795FFC',
        ]);

        $pTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);
        $pTable->addRow(400, ['tblHeader' => true]);
        $pTable->addCell(800, ['bgColor' => '795FFC'])->addText('N°', ['bold' => true, 'color' => 'FFFFFF']);
        $pTable->addCell(3200, ['bgColor' => '795FFC'])->addText('Nom et Prénom', ['bold' => true, 'color' => 'FFFFFF']);
        $pTable->addCell(2200, ['bgColor' => '795FFC'])->addText('Poste / Rôle', ['bold' => true, 'color' => 'FFFFFF']);
        $pTable->addCell(1400, ['bgColor' => '795FFC'])->addText('Statut', ['bold' => true, 'color' => 'FFFFFF']);
        $pTable->addCell(1400, ['bgColor' => '795FFC'])->addText('Pointage', ['bold' => true, 'color' => 'FFFFFF']);

        $pTable->addRow();
        $pTable->addCell(800)->addText('${part_num}');
        $pTable->addCell(3200)->addText('${part_name}');
        $pTable->addCell(2200)->addText('${part_role}');
        $pTable->addCell(1400)->addText('${part_status}');
        $pTable->addCell(1400)->addText('${part_checkin}');

        $section->addTextBreak(1);

        // Section Sujets abordés & Évolutions
        $section->addText('3. POINTS ABORDÉS ET ÉVOLUTIONS DÉCIDÉES', ['bold' => true, 'size' => 12, 'color' => '795FFC']);

        $tTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);
        $tTable->addRow(400, ['tblHeader' => true]);
        $tTable->addCell(700, ['bgColor' => '795FFC'])->addText('N°', ['bold' => true, 'color' => 'FFFFFF']);
        $tTable->addCell(2300, ['bgColor' => '795FFC'])->addText('Sujet', ['bold' => true, 'color' => 'FFFFFF']);
        $tTable->addCell(2500, ['bgColor' => '795FFC'])->addText('Situation Actuelle', ['bold' => true, 'color' => 'FFFFFF']);
        $tTable->addCell(2500, ['bgColor' => '795FFC'])->addText('Évolution / Décision', ['bold' => true, 'color' => 'FFFFFF']);
        $tTable->addCell(1000, ['bgColor' => '795FFC'])->addText('Échéance', ['bold' => true, 'color' => 'FFFFFF']);

        $tTable->addRow();
        $tTable->addCell(700)->addText('${topic_num}');
        $tTable->addCell(2300)->addText('${topic_subject}');
        $tTable->addCell(2500)->addText('${topic_situation}');
        $tTable->addCell(2500)->addText('${topic_decision}');
        $tTable->addCell(1000)->addText('${topic_due}');

        $section->addTextBreak(1);

        // Synthèse des décisions
        $section->addText('4. SYNTHÈSE DES DÉCISIONS RETENUES', ['bold' => true, 'size' => 12, 'color' => '795FFC']);
        $section->addText('${summary_decisions}');

        $section->addTextBreak(1);

        // Section Actions Kanban
        $section->addText('5. PLAN D\'ACTIONS (SUIVI KANBAN)', ['bold' => true, 'size' => 12, 'color' => '795FFC']);

        $kTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'CCCCCC', 'cellMargin' => 80]);
        $kTable->addRow(400, ['tblHeader' => true]);
        $kTable->addCell(700, ['bgColor' => '795FFC'])->addText('N°', ['bold' => true, 'color' => 'FFFFFF']);
        $kTable->addCell(4000, ['bgColor' => '795FFC'])->addText('Action / Tâche', ['bold' => true, 'color' => 'FFFFFF']);
        $kTable->addCell(2200, ['bgColor' => '795FFC'])->addText('Responsable', ['bold' => true, 'color' => 'FFFFFF']);
        $kTable->addCell(1100, ['bgColor' => '795FFC'])->addText('Échéance', ['bold' => true, 'color' => 'FFFFFF']);
        $kTable->addCell(1000, ['bgColor' => '795FFC'])->addText('État', ['bold' => true, 'color' => 'FFFFFF']);

        $kTable->addRow();
        $kTable->addCell(700)->addText('${task_num}');
        $kTable->addCell(4000)->addText('${task_title}');
        $kTable->addCell(2200)->addText('${task_resp}');
        $kTable->addCell(1100)->addText('${task_due}');
        $kTable->addCell(1000)->addText('${task_status}');

        $section->addTextBreak(1);

        // Section Notes DRH
        $section->addText('6. NOTES CONFIDENTIELLES DRH (EFFECTIFS ET CONGÉS)', ['bold' => true, 'size' => 12, 'color' => 'DC2626']);
        $section->addText('${drh_notes}', ['color' => '4B5563', 'italic' => true]);

        $section->addTextBreak(1);

        // Section Signatures / Validations Institutionnelles
        $section->addText('7. VALIDATIONS ET VISAS INSTITUTIONNELS', ['bold' => true, 'size' => 12, 'color' => '795FFC']);

        $valTable = $section->addTable(['borderSize' => 4, 'borderColor' => 'CCCCCC', 'cellMargin' => 100]);
        $valTable->addRow();
        $drhCell = $valTable->addCell(4500, ['bgColor' => 'F9FAFB']);
        $drhCell->addText('Visa du Responsable RH :', ['bold' => true]);
        $drhCell->addText('${val_drh_sign}', ['italic' => true]);

        $dirCell = $valTable->addCell(4500, ['bgColor' => 'F9FAFB']);
        $dirCell->addText('Visa de la Direction Générale :', ['bold' => true]);
        $dirCell->addText('${val_dir_sign}', ['italic' => true]);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($this->templatePath);
    }
}
