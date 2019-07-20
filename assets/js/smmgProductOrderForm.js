(function($) {
  Drupal.behaviors.smmgProductOrderFormBehavior = {
    Order: {
      discountPrice: 0,
      discountNumberOff: 0,
      priceTotal: 0,
      priceShippingTotal: 0,
      products: [],
    },

    getProducts() {
      // get first Item of array with valid key
      /** @namespace drupalSettings.productOrder */
      /** @namespace Product.numberOf  */
      /** @namespace Product.numberOf_download  */
      /** @namespace Product.price  */
      /** @namespace Product.priceDownload  */
      /** @namespace Product.priceTotal  */
      /** @namespace Product.priceTotal_download  */
      /** @namespace Product.priceShipping  */
      /** @namespace Product.priceShippingTotal  */
      /** @namespace Product.downloadFor  */
      const { products } = drupalSettings.productOrder;
      return products;
    },

    /**
     *
     * */
    updateTotal: function() {
      console.log('updateTotal');

      let discountPrice = 0;
      let discountNumber = 0;
      let priceTotal = 0;
      let priceShipping = 0;

      const products = this.updateProducts();

      console.log('products', products);

      // price Total
      for (let i = 0; i < products.length; i++) {
        priceTotal += products[i].priceTotal;
      }

      // Discount Total
      for (let i = 0; i < products.length; i++) {
        discountPrice += products[i].discountPrice;
        discountNumber += products[i].discountNumberOff;
      }

      // add Shipping
      priceShipping = this.calculateShipping(products);

      // update Price Total with Discount:
      priceTotal -= discountPrice;

      // add Shipping
      priceTotal += priceShipping;

      const order = {
        discountPrice: discountPrice,
        discountNumberOff: discountNumberOff,
        priceTotal: priceTotal,
        priceShipping: priceShipping,
      };

      Drupal.behaviors.smmgProductOrderFormBehavior.Order = order;
      this.displayResults(order);
    },

    /**
     *
     * */
    displayResults(order) {
      console.log('order', order);

      // elements
      const $priceTotal = $('.product-order-total-price-total');
      const $shippingTotal = $('.product-order-total-shipping-price');
      const $discountPrice = $('.product-order-total-discount-price');
      const $discountNumber = $('.product-order-discount-number');
      const $discountRow = $('.product-order-row-discount');

      // display discount

      // display total
      $priceTotal.text(this.convertCents(order.priceTotal));
      $shippingTotal.text(this.convertCents(order.priceShipping));

      if (order.discountNumberOff === 1) {
        $discountRow.show();
        $discountNumber.text(order.discountNumberOff + ' × Download inkl.');
        $discountPrice.text('-' + this.convertCents(order.discountPrice));
      } else if (order.discountNumberOff > 1) {
        $discountRow.show();
        $discountNumber.text(order.discountNumberOff + ' × Downloads inkl.');
        $discountPrice.text('-' + this.convertCents(order.discountPrice));
      } else {
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

    /**
     *
     * @return {*|drupalSettings.productOrder.products}
     */
    updateProducts() {
      let products = this.getProducts();

      for (let i = 0; i < products.length; i++) {
        // get Inputs
        const $elem_row_number = $(`#product-order-row-${i} select`);
        products[i].numberOf = parseInt($elem_row_number.val(), 10);

        // calculate Price Total
        products[i].priceTotal = products[i].numberOf * products[i].price;

        // Calculate Shipping
        products[i].priceShippingTotal =
          products[i].numberOf > 0 ? products[i].priceShipping : 0;

        // Display results
        const $rowTotal = $(`#product-order-row-price-total-${i}`);
        $rowTotal.text(this.convertCents(products[i].priceTotal));

        // Download
        if (products[i].download) {
          products[i - 1].downloadAvailable = true;

          products[i - 1].numberOf_download = products[i].numberOf;
        }
      }
      products = this.calculateDiscount(products);
      return products;
    },

    attach(context, settings) {
      $('#smmg-product-order-form', context)
        .once('smmgCDOrderFormBehavior')
        .each(() => {
          console.log('smmgCDOrderForm');

          // Load Products
          const products = this.getProducts();
          this.updateTotal(products);

          // Check for Number Input change
          const scope = this;
          for (let i = 0; i < products.length; i++) {
            const $input = $(`#product-order-row-${i} select`);

            // Check for Number Input change
            $input.change(() => {
              scope.updateTotal(products);
            });
          } // end for products
        });
    },
    /**
     *
     * @param products
     * @return number
     */
    calculateShipping(products) {

      // Init empty queue for all shipping prices of products
      let shippingQueue = [];

      // add shipping prices to queue if number of products is not 0
      for (let i = 0; i < products.length; i++) {
        // Calculate Shipping
        const priceShipping =
          products[i].numberOf > 0 ? products[i].priceShippingTotal : 0;

        shippingQueue.push(priceShipping);
      }

      console.log('shippingQueue: ', shippingQueue);

      // get largest item in shipping queue
      const largestShippingPrice = Math.max(...shippingQueue);

      return largestShippingPrice;
    },
    /**
     *
     *
     * @param products
     * @return {number}
     */
    calculateDiscount(products) {
      // if you buy a CD you get 1 Download for free
      //

      for (let i = 0; i < products.length; i++) {
        products[i].discountNumberOff = 0;
        products[i].discountPrice = 0;

        if (products[i].downloadAvailable) {
          const numberOfCDs = products[i].numberOf;
          const numberOfDownloads = products[i].numberOf_download;
          const priceDownload = products[i].priceDownload;
          let discountNumber = 0;
          let discountPrice = 0;

          if (numberOfCDs >= numberOfDownloads) {
            discountNumber = numberOfDownloads;
          } else {
            discountNumber = numberOfCDs;
          }

          discountPrice = discountNumberOff * priceDownload;

          products[i].discountNumberOff = discountNumberOff;
          products[i].discountPrice = discountPrice;
        }

      }
      return products;

    },
  };
})(jQuery, Drupal, drupalSettings);
