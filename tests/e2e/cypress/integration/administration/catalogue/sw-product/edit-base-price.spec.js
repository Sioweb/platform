// / <reference types="Cypress" />

import ProductPageObject from '../../../../support/pages/module/sw-product.page-object';

describe('Product: Base price', () => {
    beforeEach(() => {
        cy.loginViaApi()
            .then(() => {
                return cy.createProductFixture();
            })
            .then(() => {
                return cy.createDefaultFixture('unit');
            })
            .then(() => {
                cy.openInitialPage(`${Cypress.env('admin')}#/sw/product/index`);
            });
    });

    it('@base @catalogue: Editing product with base price', () => {
        const page = new ProductPageObject();

        // Request we want to wait for later
        cy.intercept({
            url: `${Cypress.env('apiPath')}/_action/sync`,
            method: 'POST'
        }).as('saveData');

        cy.clickContextMenuItem(
            '.sw-entity-listing__context-menu-edit-action',
            page.elements.contextMenuButton,
            `${page.elements.dataGridRow}--0`
        );

        // Set base price data
        cy.get('.sw-loader').should('not.exist');
        cy.get('.sw-product-detail__tab-specifications').scrollIntoView().click();
        cy.contains('.sw-card__title', 'Measures & packaging').scrollIntoView();
        cy.get('.sw-select-product__select_unit').typeSingleSelectAndCheck('Gramm', '.sw-select-product__select_unit');
        cy.get('.sw-product-packaging-form__purchase-unit-field').type('50');
        cy.get('.sw-product-packaging-form__pack-unit-field').type('Package');
        cy.get('.sw-product-packaging-form__pack-unit-plural-field').type('Packages');
        cy.get('.sw-product-packaging-form__reference-unit-field').type('100');

        // Save product
        cy.get(page.elements.productSaveAction).click();
        cy.wait('@saveData').its('response.statusCode').should('equal', 200);

        // Verify in storefront
        cy.visit('/');

        cy.window().then((win) => {
            /** @deprecated tag:v6.5.0 - Use `CheckoutPageObject.elements.lineItem` instead */
            const lineItemSelector = win.features['v6.5.0.0'] ? '.line-item' : '.cart-item';

            /** @deprecated tag:v6.5.0 - Use `${CheckoutPageObject.elements.lineItem}-total-price` instead */
            const lineItemTotalPriceSelector = win.features['v6.5.0.0'] ? '.line-item-total-price' : '.cart-item-price';

            cy.get('.product-price-unit').contains('Content: 50 Gramm (€99.96* / 100 Gramm)');

            cy.get('input[name=search]').type('Product name');
            cy.get('.search-suggest-container').should('be.visible');
            cy.get('.search-suggest-product-name')
                .contains('Product name')
                .click();

            cy.get('.product-detail-price-unit').contains('Content: 50 Gramm (€99.96* / 100 Gramm)');
            cy.get('.product-detail-price').contains('49.98');

            cy.get('.btn-buy').click();
            cy.get(`${lineItemTotalPriceSelector}`).contains('€49.98*');
            cy.get(`${lineItemSelector}-reference-price`).contains('€99.96* / 100 Gramm');
        });
    });
});
