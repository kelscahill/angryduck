const settings = window.wc.wcSettings.getSetting('wc_wholesale_payments_data');
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Wholesale Payments', 'wc-woocommerce-wholesale-payments');

const setPaymentPlan = (planId) => {
  const requestData = {
    plan_id: planId
  };

  // Disable the payment button.
  jQuery('.wc-block-checkout__actions_row').find('button').attr('disabled', 'disabled');

  jQuery.ajax({
    url: settings.restUrl,
    method: 'POST',
    beforeSend: function (xhr) {
      xhr.setRequestHeader('X-WP-Nonce', settings.nonce);
    },
    data: JSON.stringify(requestData),
    contentType: 'application/json',
    error: function (error) {
      console.error(error);
    },
    complete: function () {
      // Enable the payment button.
      jQuery('.wc-block-checkout__actions_row').find('button').removeAttr('disabled');

      // Change radio button to the selected plan.
      jQuery('input[name="wpay_plan"]').each(function () {
        if (this.value === planId) {
          this.checked = true;
        }
      });
    }
  });
};

const Content = (type) => {
  const handleOnChange = () => {
    const selectedPlan = document.querySelector('input[name="wpay_plan"]:checked');
    if (selectedPlan) {
      setPaymentPlan(selectedPlan.value);
    }
  }

  const mainDiv = wp.element.createElement('div', { className: 'wpay-block-payment-method' });
  const textModeContent = wp.element.createElement('p', null, window.wp.htmlEntities.decodeEntities(settings.testModeText || ''));
  const descContent = wp.element.createElement('p', null, window.wp.htmlEntities.decodeEntities(settings.description || ''));

  // List of payment plans.
  const listOfPlans = settings.paymentPlans.map((plan, i) => {
    const input = wp.element.createElement('input', {
      id: 'wpay_plan-' + plan.post.ID,
      type: 'radio',
      name: 'wpay_plan',
      value: plan.post.ID,
      checked: i === 0 ? 'checked' : '',
      onChange: handleOnChange,
    });
    const label = wp.element.createElement('label', { htmlFor: 'wpay_plan-' + plan.post.ID }, plan.post.post_title);

    return wp.element.createElement('li', { style: settings.paymentPlanItemsStyle }, [input, label]);
  });

  const paymentPlanLists = wp.element.createElement('ul', { className: 'wc-block-payment-method__plans', style: settings.paymentPlansStyle });
  paymentPlanLists.props.children = listOfPlans;

  // Insert the description and payment plans.
  mainDiv.props.children = [textModeContent, descContent, paymentPlanLists];

  // Set first plan as default.
  if (settings.paymentPlans.length > 0 && type === 'edit') {
    const defaultPlan = settings.paymentPlans[0].post.ID;
    setPaymentPlan(defaultPlan);
  }

  return mainDiv;
};

// Check if there is a payment plans available.
if (settings.paymentPlans.length > 0) {
  const Block_Gateway = {
    name: 'wc_wholesale_payments',
    label: label,
    content: Content('content'),
    edit: Content('edit'),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: settings.supports
  };

  window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
}