document.addEventListener('DOMContentLoaded', function() {
  const quantityInput = document.getElementById('quantity');
  const decreaseButton = document.getElementById('decrease-quantity');
  const increaseButton = document.getElementById('increase-quantity');
  const totalPriceElement = document.getElementById('total-price');

  if (!quantityInput || !decreaseButton || !increaseButton || !totalPriceElement) {
      console.error('ITU Shop: Quantity selector elements not found');
      return;
  }

  const price = parseFloat(quantityInput.dataset.price) || 0;
  if (isNaN(price) || price <= 0) {
      console.error('ITU Shop: Invalid price value:', quantityInput.dataset.price);
      totalPriceElement.textContent = 'CHF 0.00';
      return;
  }

  function updateTotalPrice() {
      let quantity = parseInt(quantityInput.value);
      if (isNaN(quantity) || quantity < 1) {
          quantity = 1;
          quantityInput.value = 1;
      } else if (quantity > 10) {
          quantity = 10;
          quantityInput.value = 10;
      }
      const total = (price * quantity).toFixed(2);
      totalPriceElement.textContent = `CHF ${total}`;
      console.log('ITU Shop: Total price updated:', { quantity, total });
  }

  decreaseButton.addEventListener('click', function() {
      let quantity = parseInt(quantityInput.value);
      if (quantity > 1) {
          quantityInput.value = quantity - 1;
          updateTotalPrice();
      }
  });

  increaseButton.addEventListener('click', function() {
      let quantity = parseInt(quantityInput.value);
      if (quantity < 10) {
          quantityInput.value = quantity + 1;
          updateTotalPrice();
      }
  });

  quantityInput.addEventListener('input', function() {
      updateTotalPrice();
  });

  quantityInput.addEventListener('change', function() {
      let quantity = parseInt(quantityInput.value);
      if (isNaN(quantity) || quantity < 1) {
          quantityInput.value = 1;
      } else if (quantity > 10) {
          quantityInput.value = 10;
      }
      updateTotalPrice();
  });

  // Initial total price calculation
  updateTotalPrice();
});