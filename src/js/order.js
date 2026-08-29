(() => {
  const state = {
    product: null,
    qty: 1,
  };

  const els = {
    productImage: document.getElementById('productImage'),
    productName: document.getElementById('productName'),
    productDesc: document.getElementById('productDesc'),
    productPrice: document.getElementById('productPrice'),
    orderTotal: document.getElementById('orderTotal'),
    qtyValue: document.getElementById('qtyValue'),
    qtyMinus: document.getElementById('qtyMinus'),
    qtyPlus: document.getElementById('qtyPlus'),
    stockNotice: document.getElementById('stockNotice'),
    demoBanner: document.getElementById('demoBanner'),
    checkoutForm: document.getElementById('checkoutForm'),
    formError: document.getElementById('formError'),
    payButton: document.getElementById('payButton'),
  };

  function formatRupees(paise) {
    return '₹' + (paise / 100).toFixed(2);
  }

  function showError(message) {
    els.formError.textContent = message;
    els.formError.classList.add('is-visible');
  }

  function clearError() {
    els.formError.classList.remove('is-visible');
  }

  const LOW_STOCK_THRESHOLD = 4;

  function updateTotal() {
    if (!state.product) return;
    els.orderTotal.textContent = formatRupees(state.product.price_paise * state.qty);
  }

  function updateStockUI() {
    if (!state.product) return;
    const stock = state.product.stock;
    const outOfStock = stock <= 0;

    els.qtyMinus.disabled = outOfStock || state.qty <= 1;
    els.qtyPlus.disabled = outOfStock || state.qty >= stock;
    els.qtyValue.disabled = outOfStock;
    els.payButton.disabled = outOfStock;
    els.payButton.textContent = outOfStock ? 'Out of stock' : 'Order now';

    if (outOfStock) {
      els.stockNotice.textContent = 'Out of stock';
      els.stockNotice.classList.add('stock-notice--out');
      els.stockNotice.style.display = 'block';
    } else if (stock <= LOW_STOCK_THRESHOLD) {
      els.stockNotice.textContent = `Hurry, only ${stock} left!`;
      els.stockNotice.classList.remove('stock-notice--out');
      els.stockNotice.style.display = 'block';
    } else {
      els.stockNotice.style.display = 'none';
    }
  }

  function setQty(next) {
    const max = Math.max(1, state.product ? state.product.stock : 1);
    state.qty = Math.min(max, Math.max(1, next));
    els.qtyValue.value = state.qty;
    updateTotal();
    updateStockUI();
  }

  els.qtyMinus.addEventListener('click', () => setQty(state.qty - 1));
  els.qtyPlus.addEventListener('click', () => setQty(state.qty + 1));
  els.qtyValue.addEventListener('change', () => setQty(parseInt(els.qtyValue.value, 10) || 1));
  els.qtyValue.addEventListener('input', () => {
    const digitsOnly = els.qtyValue.value.replace(/\D/g, '');
    if (digitsOnly !== els.qtyValue.value) els.qtyValue.value = digitsOnly;
  });

  async function loadProduct() {
    const { product } = await Api.get('api/product.php');
    state.product = product;
    els.productImage.src = product.image_path;
    els.productImage.alt = product.name;
    els.productName.textContent = product.name;
    els.productDesc.textContent = product.description || '';
    els.productPrice.textContent = formatRupees(product.price_paise);
    if (product.stock <= 0) state.qty = 0;
    updateTotal();
    updateStockUI();
  }

  async function initConfig() {
    const config = await Api.getConfig();
    if (config.demoMode) {
      els.demoBanner.style.display = 'block';
    }
    return config;
  }

  // ---- Demo payment modal ----
  const demoBackdrop = document.getElementById('demoModalBackdrop');
  const demoModal = document.getElementById('demoModal');
  const demoAmount = document.getElementById('demoAmount');
  const demoPayAmount = document.getElementById('demoPayAmount');
  const demoPaySuccess = document.getElementById('demoPaySuccess');
  const demoCancel = document.getElementById('demoCancel');

  function openDemoModal(amountPaise, onConfirm) {
    demoModal.classList.remove('is-loading');
    demoAmount.textContent = formatRupees(amountPaise);
    demoPayAmount.textContent = (amountPaise / 100).toFixed(2);
    demoBackdrop.style.display = 'flex';

    const cleanup = () => {
      demoBackdrop.style.display = 'none';
      demoPaySuccess.removeEventListener('click', onPay);
      demoCancel.removeEventListener('click', onCancel);
    };

    const onPay = async () => {
      demoModal.classList.add('is-loading');
      await onConfirm();
      cleanup();
    };

    const onCancel = () => cleanup();

    demoPaySuccess.addEventListener('click', onPay);
    demoCancel.addEventListener('click', onCancel);
  }

  async function finishOrder() {
    els.payButton.disabled = true;
    els.payButton.textContent = 'Payment received ✓';

    const successMsg = document.createElement('p');
    successMsg.className = 'form-success is-visible';
    successMsg.textContent = 'Payment successful — thank you for your order! We\'ll email you updates as it ships.';
    els.checkoutForm.prepend(successMsg);
    els.checkoutForm.querySelectorAll('input, button').forEach((el) => (el.disabled = true));

    setTimeout(() => {
      window.location.href = 'index.html';
    }, 2500);
  }

  els.checkoutForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearError();

    if (!state.product || state.product.stock <= 0) {
      showError('This product is out of stock.');
      return;
    }

    const formData = new FormData(els.checkoutForm);
    const shipping = Object.fromEntries(formData.entries());

    els.payButton.disabled = true;
    els.payButton.textContent = 'Starting payment…';

    try {
      const checkout = await Api.post('api/checkout.php', {
        productId: state.product.id,
        quantity: state.qty,
        shipping,
      });

      if (checkout.demoMode) {
        openDemoModal(checkout.amountPaise, async () => {
          await Api.post('api/verify-payment.php', {
            orderId: checkout.orderId,
            razorpayOrderId: checkout.razorpayOrderId,
            razorpayPaymentId: '',
          });
          await finishOrder();
        });
        els.payButton.disabled = false;
        els.payButton.textContent = 'Order now';
        return;
      }

      const config = await Api.getConfig();
      const rzp = new Razorpay({
        key: checkout.razorpayKeyId,
        amount: checkout.amountPaise,
        currency: config.currency,
        name: config.siteName,
        description: checkout.productName,
        order_id: checkout.razorpayOrderId,
        prefill: { name: shipping.name, email: shipping.email, contact: shipping.phone },
        theme: { color: '#0e2b03' },
        handler: async (response) => {
          await Api.post('api/verify-payment.php', {
            orderId: checkout.orderId,
            razorpayOrderId: response.razorpay_order_id,
            razorpayPaymentId: response.razorpay_payment_id,
            razorpaySignature: response.razorpay_signature,
          });
          await finishOrder();
        },
        modal: {
          ondismiss: () => {
            els.payButton.disabled = false;
            els.payButton.textContent = 'Order now';
          },
        },
      });
      rzp.open();
    } catch (err) {
      showError(err.message);
      els.payButton.disabled = false;
      els.payButton.textContent = 'Order now';
    }
  });

  (async function init() {
    try {
      await Promise.all([loadProduct(), initConfig()]);
    } catch (err) {
      showError(err.message);
    }
  })();
})();
