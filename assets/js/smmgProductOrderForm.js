(function($) {
  Drupal.behaviors.smmgCDOrderFormBehavior = {
    getProducts() {
      // get first Item of array with valid key
      /** @namespace drupalSettings.product_order */
      const { products } = drupalSettings.product_order;
      return products;
    },

    /**
     *
     * */
    updateTotal: function(products) {
      console.log('updateTotal');
      console.log('products', products);

      let vOrder = {
        discountPrice: 0,
        discountNumber: 0,
        priceTotal: 0,
        shippingTotal: 0,
        products: [],
      };

      // check Rows
      for (let i = 0; i < products.length; i++) {
        const rowNumber = i + 1;

        // Define virtual Product
        let vProduct = {
          number: 0,
          price: 0,
          shipping: 0,
          priceTotal: 0,
          shippingTotal: 0,
        };

        // Get Data from drupalSettings.product_order
        vProduct.price = parseInt(products[i].price, 10);
        vProduct.shipping = parseInt(products[i].shipping, 10);

        // get Inputs
        const $elem_row_number = $(`#product_order-row-${rowNumber} select`);
        vProduct.number = parseInt($elem_row_number.val(), 10);

        // calculate Price
        vProduct.priceTotal = vProduct.number * vProduct.price;

        // Add Total to Order
        vOrder.priceTotal += vProduct.priceTotal;

        // Calculate Shipping
        vProduct.shippingTotal = vProduct.number > 0 ? vProduct.shipping : 0;

        // check if new shipping is more then current shipping
        if (
          vOrder.shippingTotal === 0 ||
          vProduct.shippingTotal > vOrder.shippingTotal
        ) {
          vOrder.shippingTotal += vProduct.shippingTotal;
        }
        console.log('vProduct', vProduct);

        // Add updated Product to Order
        vOrder.products.push(vProduct);
      }

      // add Shipping
      vOrder.priceTotal += vOrder.shippingTotal;

      // calculate Discount
      // digital-Download minus number-of-CDs * Download-Price
      // const discountNumber = vOrder.products[1].number - vOrder.products[0].number;
      let discountNumber = 0;
      const numberOfCDs = vOrder.products[0].number;
      const numberOfDownloads = vOrder.products[1].number;

      // more Download as CD: only number of downloads:
      if (numberOfDownloads >= numberOfCDs) {
        discountNumber = numberOfCDs;
      } else {
        discountNumber = numberOfDownloads;
      }
      vOrder.discountNumber = discountNumber;
      // discount total
      const discount = discountNumber * vOrder.products[1].price;


      // add to virtual Order
      vOrder.discountPrice = discount;

      // update Price Total with Discount:
      vOrder.priceTotal -= discount;

      this.displayResults(vOrder);
    },

    /**
     *
     * */
    displayResults(vOrder) {
      console.log('vOrder', vOrder);

      // elements
      const $priceTotal = $('.product_order-table-total-price');
      const $shippingTotal = $('.product_order-total-shipping-price');
      const $discountPrice = $('.product_order-total-discount-price');
      const $discountNumber = $('.product_order-discount-number');
      const $discountRow = $('.product_order-row-discount');

      // display row
      for (let i = 0; i < vOrder.products.length; i++) {
        // Row number
        const rowNumber = i + 1;

        // display total of each row
        const $rowTotal = $(`#product_order-row-price-total-${rowNumber}`);
        $rowTotal.text(this.convertCents(vOrder.products[i].priceTotal));
      }

      // display discount

      // display total
      $priceTotal.text(this.convertCents(vOrder.priceTotal));
      $shippingTotal.text(this.convertCents(vOrder.shippingTotal));

      if(vOrder.discountNumber === 1){
        $discountRow.show();
        $discountNumber.text(vOrder.discountNumber + ' ink.');
        $discountPrice.text('-' + this.convertCents(vOrder.discountPrice));

      }else if(vOrder.discountNumber > 1){
        $discountRow.show();
        $discountNumber.text(vOrder.discountNumber + ' ink.');
        $discountPrice.text('-' + this.convertCents(vOrder.discountPrice));

      }else{
        $discountRow.hide();
      }

    },

    /**
     *
     * */
    convertCents(cents) {
      const int = parseInt(cents, 10);
      const result = int / 100;
      return result.toFixed(2);
    },

    attach(context, settings) {
      $('#smmg-cd-order-form', context)
        .once('smmgCDOrderFormBehavior')
        .each(() => {
          console.log('smmgCDOrderForm');

          // Load Products
          const products = this.getProducts();
          this.updateTotal(products);

          // Check for Number Input change
          const scope = this;
          for (let i = 0; i < products.length; i++) {
            const rowNumber = i + 1;
            const $number = $(`#product_order-row-${rowNumber} select`);
            // console.log('$number', $number);

            // Check for Number Input change
            $number.change(() => {
              scope.updateTotal(products);
            });
          } // end for products
        });
    },
  };
})(jQuery, Drupal, drupalSettings);
