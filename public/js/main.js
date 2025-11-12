document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('.alert');
    flashMessages.forEach(function(message) {
        setTimeout(function() {
            message.style.opacity = '0';
            setTimeout(function() {
                message.remove();
            }, 300);
        }, 5000);
    });
});

function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this?');
}

function toggleSaveJob(jobId) {
    fetch('ajax/toggle-save-job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ job_id: jobId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'An error occurred');
        }
    });
}
