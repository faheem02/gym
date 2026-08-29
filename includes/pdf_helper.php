<!-- html2pdf.js CDN for client-side PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function exportElementToPDF(elementId, filename, optOverrides) {
    var element = document.getElementById(elementId);
    if (!element) {
        alert('Print area not found.');
        return;
    }
    
    var btn = event && event.target ? event.target.closest('button') : null;
    var originalHTML = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating PDF...';
    }

    var defaultOpt = {
        margin:       [8, 8, 8, 8],
        filename:     filename || 'document.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    var opt = Object.assign({}, defaultOpt, optOverrides || {});

    html2pdf().set(opt).from(element).save().then(function() {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    }).catch(function(err) {
        console.error('PDF Generation Error:', err);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
        alert('Could not generate PDF. You can also use Print -> Save as PDF.');
    });
}
</script>
