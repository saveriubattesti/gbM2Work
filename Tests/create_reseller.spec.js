/// <reference types="cypress" />

const DEFAULT_SELECTED_RESELLER_TYPE = 'classic'
const DEFAULT_UNSELECTED_RESELLER_TYPE = 'classic-shop'
const VAT_SPAIN_SELECT_NUMBER = '198'
const VAT_VALID_NUMBER_EXAMPLE = 'ESB41730557'
const VAT_INVALID_NUMBER_EXAMPLE = '00000000000'
const VAT_ITALY_SELECT_NUMBER = '106'
let pricesWithVAT
let pricesWithoutVAT

describe(`Create reseller form on ${Cypress.env("extension")}`, function () {
    beforeEach(function () {
        cy.viewport("macbook-15")
        cy.fixture('portal.json').as('conf')
        cy.visit(`https://fr.goodbarber.com${Cypress.env("extension")}/create/reseller`)
        cy.server()
        cy.route('POST', '/create/reseller/checkIntracom/').as('checkIntracom')
    })

    it(`Monthly and yearly prices should be different`, function ()  {
        cy.get(`[data-cy="yearly-plan"] label span`).then(function ($yearlyPrices) {
            cy.get(`[data-cy="monthly-plan"] label span`).then(function ($monthlyPrices) {
                expect($yearlyPrices.text()).to.not.equal($monthlyPrices.text())
            })
        })
    })

    it(`Prices should be displayed according to the switch`, () => {
        cy.get(`[data-cy="pricing-monthly-annually"] input`).then(($defaultPeriodicity) => {
            const initialCheckedPeriodicity = $defaultPeriodicity.prop('checked') ? 'yearly' : 'monthly'
            const inverse = initialCheckedPeriodicity === 'yearly' ? 'monthly' : 'yearly'
            cy.get(`[data-cy="${initialCheckedPeriodicity}-plan"]`).should('be.visible')
            cy.get(`[data-cy="${inverse}-plan"]`).should('not.be.visible')
            cy.get(`[data-cy="pricing-monthly-annually"]`).click()
            cy.get(`[data-cy="${inverse}-plan"]`).should('be.visible')
            cy.get(`[data-cy="${initialCheckedPeriodicity}-plan"]`).should('not.be.visible')
        })
    })

    it(`The reseller type clicked by the user should be the one selected`, function () {
        cy.get(`[data-cy="yearly-reseller-${DEFAULT_SELECTED_RESELLER_TYPE}"]`).should('have.class', 'active')
        cy.get(`[data-cy="yearly-reseller-${DEFAULT_UNSELECTED_RESELLER_TYPE}"]`).should('not.have.class', 'active')
        cy.get(`[data-cy="yearly-reseller-${DEFAULT_UNSELECTED_RESELLER_TYPE}"]`).click()
        cy.get(`[data-cy="yearly-reseller-${DEFAULT_SELECTED_RESELLER_TYPE}"]`).should('not.have.class', 'active')
        cy.get(`[data-cy="yearly-reseller-${DEFAULT_UNSELECTED_RESELLER_TYPE}"]`).should('have.class', 'active')
    })

    it(`If existing account ID and good password, opt-in should be hidden, user name, account ID and unlog link should be displayed`, function () {
        cy.get(`[data-cy="email"]`).type(this.conf.userWithInfos.accountId)
        cy.get(`[data-cy="password"]`).clear().type(this.conf.userWithInfos.password)
        cy.get(`[data-cy="email"]`).should('not.exist')
        cy.get(`[data-cy="password"]`).should('not.exist')
        cy.get(`[data-cy="prenom"]`).should('have.value', this.conf.userWithInfos.prenom)
        cy.get(`[data-cy="nom"]`).should('have.value', this.conf.userWithInfos.nom)
        cy.get(`[data-cy="logout"]`).should('be.visible')
        cy.get(`[data-cy="account-id"]`).should('be.visible')
    })

    it(`If account ID has wrong password, error message should be displayed`, function () {
        cy.get(`[data-cy="div-credentials"] p.help-block`).should('not.exist')
        cy.get(`[data-cy="email"]`).type(this.conf.user.existingAccountId)
        cy.get(`[data-cy="password"]`).clear().type(this.conf.wrongPasswords[2])
        cy.get(`[data-cy="div-credentials"] p.help-block`).should('be.visible')
    })

    it(`If not existing account ID, error message should not be displayed`, function () {
        cy.get(`[data-cy="email"]`).type(this.conf.user.notExistingAccountId)
        cy.get(`[data-cy="div-credentials"] p.help-block`).should('not.exist')
    })

    it(`If already logged, user name, account ID and unlog link should be displayed. Clicking on unlog should display account ID and password fields`, function () {
        cy.visit(`https://fr.goodbarber.com${Cypress.env("extension")}/login`)
        cy.get('#login').type(this.conf.userWithInfos.accountId)
        cy.get('#password').type(`${this.conf.userWithInfos.password}{enter}`)
        cy.visit(`https://fr.goodbarber.com${Cypress.env("extension")}/create/reseller`)
        cy.get(`[data-cy="prenom"]`).should('have.value', this.conf.userWithInfos.prenom)
        cy.get(`[data-cy="nom"]`).should('have.value', this.conf.userWithInfos.nom)
        cy.get(`[data-cy="logout"]`).should('be.visible')
        cy.get(`[data-cy="account-id"]`).should('be.visible')
        cy.get(`[data-cy="logout"]`).click()
        cy.get(`[data-cy="email"]`).should('be.visible')
        cy.get(`[data-cy="password"]`).should('be.visible')
    })

    it(`If VAT OK, prices should change`, function () {
        cy.get(`[data-cy="yearly-plan"] label span`).then(function ($prices) {
            pricesWithoutVAT = $prices.text()
            cy.get(`[data-cy="country"]`).select(VAT_SPAIN_SELECT_NUMBER)
            cy.get(`[data-cy="vat-number"]`).type(VAT_VALID_NUMBER_EXAMPLE)
            cy.wait('@checkIntracom')
            cy.wait(0)
            cy.get(`[data-cy="yearly-plan"] label span`).then(function ($prices) {
                pricesWithVAT = $prices.text()
                expect(pricesWithoutVAT).to.not.equal(pricesWithVAT)
            })
        })
    })

    it(`If VAT not OK, prices should change and error message should be displayed`, function () {
        cy.get(`[data-cy="country"]`).select(VAT_SPAIN_SELECT_NUMBER)
        cy.get(`[data-cy="vat-number"]`).type(VAT_INVALID_NUMBER_EXAMPLE)
        cy.wait('@checkIntracom')
        cy.wait(0)
        cy.get(`[data-cy="yearly-plan"] label span`).then(function ($prices) {
            pricesWithoutVAT = $prices.text()
            expect(pricesWithoutVAT).to.not.equal(pricesWithVAT)
            cy.get(`[data-cy="div-tva"] p.help-block`).should('be.visible')
        })
    })

    it(`If country changes and VAT not OK, prices should change and error message should be displayed`, function () {
        cy.get(`[data-cy="country"]`).select(VAT_SPAIN_SELECT_NUMBER)
        cy.get(`[data-cy="vat-number"]`).type(VAT_INVALID_NUMBER_EXAMPLE)
        cy.wait('@checkIntracom')
        cy.wait(0)
        cy.get(`[data-cy="country"]`).select(VAT_ITALY_SELECT_NUMBER)
        cy.get(`[data-cy="yearly-plan"] label span`).then(function ($prices) {
            expect($prices.text()).not.to.equal(pricesWithVAT)
            cy.get(`[data-cy="div-tva"] p.help-block`).should('be.visible')
        })
    })

    it(`If country changes and VAT OK, prices should change`, function () {
        cy.get(`[data-cy="country"]`).select(VAT_SPAIN_SELECT_NUMBER)
        cy.get(`[data-cy="vat-number"]`).type(VAT_VALID_NUMBER_EXAMPLE)
        cy.wait('@checkIntracom')
        cy.wait(0)
        cy.get(`[data-cy="country"]`).select(VAT_ITALY_SELECT_NUMBER)
        cy.get(`[data-cy="yearly-plan"] label span`).then(function ($prices) {
            expect($prices.text()).not.to.equal(pricesWithoutVAT)
        })
    })

    it(`Email, password, first name, name, agency, adress, zip code and city fields should be mandatory and have an error message`, function () {
        const fields = ["email", "password", "prenom", "nom", "societe", "adresse", "cp", "ville"]
        fields.forEach((field) => {
            cy.get(`[data-cy="${field}"]:required`).should('have.length', 1)
            cy.get(`[data-cy="${field}"]:invalid`).should('have.length', 1)
        })
    })

    it(`When submitting the form with a filled form, the loading page should be displayed. The submit button should not be clickable twice.`, function () {
        cy.get(`[data-cy="email"]`).type(this.conf.userWithInfos.accountId)
        cy.get(`[data-cy="password"]`).type(this.conf.userWithInfos.password)
        const fields = ["prenom", "nom", "societe", "adresse", "ville"]
        fields.forEach((field) => {
            cy.get(`[data-cy="${field}"]`).type("toto")
        })
        cy.get(`[data-cy="cp"]`).type('20000')
        cy.get(`[data-cy="create-reseller-app-btn"]`).click()
        cy.get(`[data-cy="create-reseller-app-btn"]`).should('not.exist')
        cy.get(`[data-cy="create-reseller-loading"]`).should('be.visible')
    })
})
