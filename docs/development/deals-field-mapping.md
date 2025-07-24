# Deals Module Field Mapping

## Basic Information

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| name | varchar(255) | Yes | Deal/Company name |
| status | enum | Yes | Current deal stage |
| source | enum | No | Lead source |
| deal_value | currency | No | Estimated deal value |
| probability | int | No | Close probability % |
| expected_close_date | date | No | Expected closing date |

## Financial Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| asking_price_c | currency | No | Seller's asking price |
| ttm_revenue_c | currency | No | Trailing 12-month revenue |
| ttm_ebitda_c | currency | No | Trailing 12-month EBITDA |
| sde_c | currency | No | Seller's discretionary earnings |
| proposed_valuation_c | currency | No | Calculated/proposed valuation |
| target_multiple_c | float | No | Target EBITDA multiple |

## Capital Stack

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| equity_c | currency | No | Equity portion |
| senior_debt_c | currency | No | Senior debt amount |
| seller_note_c | currency | No | Seller financing |

## Tracking Fields

| Field Name | Type | Required | Description |
|------------|------|----------|-------------|
| at_risk_status | enum | Auto | Normal/Warning/Alert |
| days_in_stage | int | Auto | Days in current stage |
| last_activity_date | datetime | Auto | Last interaction |

## Status Values

- sourcing
- initial_contact  
- nda_signed
- info_received
- initial_analysis
- loi_submitted
- loi_accepted
- due_diligence
- final_negotiation
- closed_won
- closed_lost

## Source Values

- email
- phone
- referral
- broker
- direct_outreach
- website
- conference
- other