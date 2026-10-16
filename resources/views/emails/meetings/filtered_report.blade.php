<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Compte-rendu de réunion et vos actions</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .header { background-color: #0f172a; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0 0 8px 0; font-size: 20px; font-weight: 700; color: #ffffff; }
        .header p { margin: 0; color: #94a3b8; font-size: 14px; }
        .content { padding: 24px; line-height: 1.6; }
        .summary-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 16px 0; font-size: 14px; }
        .section-title { font-size: 15px; font-weight: 700; color: #334155; margin: 20px 0 10px 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; }
        .task-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px; margin-bottom: 10px; }
        .task-title { font-weight: 600; color: #166534; font-size: 14px; margin-bottom: 4px; }
        .task-meta { font-size: 12px; color: #475569; display: flex; justify-content: space-between; }
        .empty-tasks { background: #f1f5f9; color: #64748b; padding: 14px; border-radius: 8px; font-size: 13px; text-align: center; }
        .footer { padding: 16px 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <p>{{ \App\Models\CompanySetting::get('company_name', 'Wakati') }} — Synthèse & Suivi des Décisions</p>
            <h1>Compte-Rendu de Réunion</h1>
        </div>
        <div class="content">
            <p>Bonjour <strong>{{ $participant->name }}</strong>,</p>
            <p>La réunion <strong>« {{ $meeting->title }} »</strong> tenue le <strong>{{ \Carbon\Carbon::parse($meeting->date)->translatedFormat('d F Y') }}</strong> a été clôturée avec succès.</p>

            <div class="summary-box">
                <p style="margin: 0 0 8px 0;"><strong>Ordre du jour :</strong><br>{{ $meeting->agenda }}</p>
                <p style="margin: 0;"><strong>Synthèse des décisions retenues :</strong><br>
                {{ $meeting->summary_decisions ?: 'Aucune décision générale particulière n\'a été consignée.' }}
                </p>
            </div>

            <div class="section-title">
                🎯 Vos Actions & Tâches Personnelles à Réaliser
            </div>

            @if($userTasks->count() > 0)
                <p style="font-size: 13px; color: #475569; margin-top: 0;">
                    Voici les actions issues de cette réunion qui vous ont été directement assignées dans votre Kanban :
                </p>
                @foreach($userTasks as $task)
                    <div class="task-card">
                        <div class="task-title">{{ $task->title }}</div>
                        @if($task->description)
                            <div style="font-size: 13px; color: #334155; margin-bottom: 6px;">{{ $task->description }}</div>
                        @endif
                        <div class="task-meta">
                            <span>Échéance : <strong>{{ $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('d/m/Y') : 'Non définie' }}</strong></span>
                            <span>Statut : <strong>{{ match($task->status) { 'todo' => 'À faire', 'in_progress' => 'En cours', 'review' => 'En révision', 'done' => 'Terminé', default => $task->status } }}</strong></span>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="empty-tasks">
                    Vous n'avez aucune action ou tâche opérationnelle directement assignée à votre nom pour cette séance.
                </div>
            @endif

            <p style="margin-top: 24px; font-size: 12px; color: #94a3b8; font-style: italic;">
                Note de confidentialité : Ce message contient uniquement les résolutions publiques et les tâches qui vous sont personnellement destinées.
            </p>
        </div>
        <div class="footer">
            {{ \App\Models\CompanySetting::get('company_name', 'Wakati') }} Mono-Entreprise — Gestion Institutionnelle & RH
        </div>
    </div>
</body>
</html>
