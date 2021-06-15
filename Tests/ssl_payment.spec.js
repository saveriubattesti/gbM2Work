/// <reference types="cypress" />

Cypress.on('uncaught:exception', (err, runnable) => {
    return false;
})

describe(`ssl`, function () {
    beforeEach(() => {
        cy.viewport("macbook-15")
        cy.fixture('ssl.json').as('user').then(function () {
            cy.visit(`https://${this.user.id}.goodbarber.app${Cypress.env("extension")}/manage/settings/billing/paymentinfo/?force_log=1`)
            cy.get(`#recap-plan a:first`).click()
            cy.get(`#form-payment-recap button`).click()
        })
    })

    it(`Credit card form should be visible`, function () {
        cy.get(`[data-cy="payment-fields"]`).should('be.visible')
    })

    it(`Input name should not be empty`, function () {
        cy.get(`[data-cy="payment-fields"] #full_name`).should('not.have.value', '')
    })

    it(`Errors should not be visible`, function () {
        cy.get(`[data-cy="payment-fields"]`).find('.help-block:visible').should('have.length', 0)
    })

    it(`Submitting an empty form should display 2 mandatory errors`, function () {
        cy.get(`[data-cy="div-pay-now"] button`).click()
        cy.get(`[data-cy="payment-fields"]`).find('.help-block:visible').should('have.length', 2)
    })

    it(`If user fill the credit card number field, the error associated should disappear`, function () {
        cy.get(`[data-cy="div-pay-now"] button`).click()
        cy.get(`[data-cy="payment-fields"] #fNumCB`).type('0')
        cy.get(`[data-cy="payment-fields"]`).find('.help-block:visible').should('have.length', 1)
    })

    it(`If user fill the CVV number field, the error associated should disappear`, function () {
        cy.get(`[data-cy="div-pay-now"] button`).click()
        cy.get(`[data-cy="payment-fields"] #fCvv`).type('0')
        cy.get(`[data-cy="payment-fields"]`).find('.help-block:visible').should('have.length', 1)
    })

    it(`If user submit an uncompleted form, submit button should be inactive, until the form is completed`, function () {
        cy.get(`[data-cy="div-pay-now"] button`).click()
        cy.get(`[data-cy="div-pay-now"] button`).should('be.disabled')
        cy.get(`[data-cy="payment-fields"] #fNumCB`).type('0')
        cy.get(`[data-cy="payment-fields"] #fCvv`).type('0')
        cy.get(`[data-cy="div-pay-now"] button`).should('not.be.disabled')
    })
})