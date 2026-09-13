<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/rbac.php';

requireLogin();
requirePermission('pipeline.view');

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title>Zopa CRM - Sales Pipeline</title>
    <style>
        body { font-family: sans-serif; background-color: #F1EDED; color: #1E1C1C; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .header { background-color: white; padding: 1rem 2rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .header-nav a { margin-left: 1rem; color: #CF1F3C; text-decoration: none; font-weight: bold; }

        .board-container { flex: 1; padding: 1rem; display: flex; overflow-x: auto; gap: 1rem; }

        .pipeline-column {
            background: #e2e8f0;
            min-width: 300px;
            max-width: 300px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
        }
        .pipeline-column-header {
            padding: 1rem;
            font-weight: bold;
            border-bottom: 2px solid #cbd5e1;
            display: flex; justify-content: space-between; align-items: center;
        }
        .pipeline-column-body {
            padding: 1rem;
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            min-height: 200px;
        }

        .lead-card {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            cursor: grab;
            border-left: 4px solid #94a3b8;
        }
        .lead-card:active { cursor: grabbing; }

        .lead-card.Hot { border-left-color: #ef4444; }
        .lead-card.Warm { border-left-color: #f59e0b; }
        .lead-card.Cold { border-left-color: #3b82f6; }

        .lead-card strong { display: block; font-size: 1.1rem; margin-bottom: 0.25rem; }
        .lead-card span { display: block; font-size: 0.9rem; color: #64748b; }
        .lead-card a { color: #CF1F3C; text-decoration: none; font-size: 0.9rem; display: inline-block; margin-top: 0.5rem; }

        .dropzone { border: 2px dashed #94a3b8; background: #f8fafc; }
    </style>
</head>
<body>

<div class="header">
    <h1>Sales Pipeline</h1>
    <div class="header-nav">
        <a href="/dashboard.php">Back to Dashboard</a>
    </div>
</div>

<div class="board-container" id="pipelineBoard">
    Loading pipeline...
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function escapeHTML(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

let boardData = [];

function loadBoard() {
    fetch('/api/pipeline.php?action=board')
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                boardData = res.data;
                renderBoard();
            } else {
                document.getElementById('pipelineBoard').innerHTML = 'Error loading pipeline.';
            }
        });
}

function renderBoard() {
    const container = document.getElementById('pipelineBoard');
    container.innerHTML = '';

    boardData.forEach(stage => {
        let leadsHtml = '';

        stage.leads.forEach(lead => {
            leadsHtml += `
                <div class="lead-card ${escapeHTML(lead.lead_temperature)}" draggable="true" ondragstart="dragStart(event, ${lead.id})">
                    <strong>${escapeHTML(lead.name)}</strong>
                    <span>${escapeHTML(lead.mobile)}</span>
                    <span>Score: ${escapeHTML(lead.lead_score)} | ${escapeHTML(lead.lead_temperature)}</span>
                    <a href="/leads/view.php?id=${lead.id}">View Details</a>
                </div>
            `;
        });

        container.innerHTML += `
            <div class="pipeline-column" data-stage-id="${stage.id}" ondragover="dragOver(event)" ondrop="drop(event, ${stage.id})" ondragenter="dragEnter(event)" ondragleave="dragLeave(event)">
                <div class="pipeline-column-header">
                    <span>${escapeHTML(stage.name)}</span>
                    <span style="font-size: 0.8rem; background: white; padding: 2px 6px; border-radius: 10px;">${stage.leads.length}</span>
                </div>
                <div class="pipeline-column-body">
                    ${leadsHtml}
                </div>
            </div>
        `;
    });
}

let draggedLeadId = null;

function dragStart(e, leadId) {
    draggedLeadId = leadId;
    e.dataTransfer.effectAllowed = 'move';
}

function dragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function dragEnter(e) {
    e.preventDefault();
    const column = e.target.closest('.pipeline-column');
    if (column) column.classList.add('dropzone');
}

function dragLeave(e) {
    const column = e.target.closest('.pipeline-column');
    if (column) column.classList.remove('dropzone');
}

function drop(e, targetStageId) {
    e.preventDefault();

    // Remove dropzone highlight from all
    document.querySelectorAll('.pipeline-column').forEach(col => col.classList.remove('dropzone'));

    if (!draggedLeadId) return;

    // Find the lead in our local state and update it immediately for UI feel
    let sourceStageIdx = null;
    let leadObj = null;

    for(let i=0; i<boardData.length; i++) {
        const leadIdx = boardData[i].leads.findIndex(l => l.id == draggedLeadId);
        if(leadIdx !== -1) {
            sourceStageIdx = i;
            leadObj = boardData[i].leads[leadIdx];
            // If dropping in same column, do nothing
            if (boardData[i].id == targetStageId) return;
            boardData[i].leads.splice(leadIdx, 1);
            break;
        }
    }

    if (leadObj) {
        const targetStageIdx = boardData.findIndex(s => s.id == targetStageId);
        if(targetStageIdx !== -1) {
            leadObj.pipeline_stage_id = targetStageId;
            boardData[targetStageIdx].leads.push(leadObj);
            renderBoard();

            // Persist to backend
            const fd = new FormData();
            fd.append('action', 'move_lead');
            fd.append('lead_id', draggedLeadId);
            fd.append('stage_id', targetStageId);
            fd.append('csrf_token', csrfToken);

            fetch('/api/pipeline.php', {
                method: 'POST',
                body: fd
            }).then(r => r.json()).then(res => {
                if (res.status !== 'success') {
                    alert('Error moving lead: ' + res.message);
                    loadBoard(); // Reload from source of truth on error
                }
            });
        }
    }
    draggedLeadId = null;
}

document.addEventListener('DOMContentLoaded', loadBoard);
</script>

</body>
</html>
