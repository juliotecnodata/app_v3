(() => {
  const btnToast = document.getElementById('btnMockToast');
  const btnSwal = document.getElementById('btnMockSwal');

  btnToast?.addEventListener('click', () => {
    if (typeof showToast === 'function') {
      showToast('Toast de teste com cores da marca', 'success');
    }
  });

  btnSwal?.addEventListener('click', () => {
    if (window.Swal) {
      Swal.fire({ icon: 'info', title: 'Alerta de teste', text: 'UI mock para ajuste rápido.' });
    }
  });
})();
