# Tap Native SDK — Instant Provider Split Payout Integration Guide (Flutter)

## 1. Overview & Objective

This guide explains how the Flutter app handles **instant provider payouts (`destination_id` / Tap Marketplace Split)** when using Tap Payments' Native Flutter SDK (`checkout_flutter`).

### How It Works:
```
1. Customer taps "Pay Now"
        │
        ▼
2. Flutter calls `POST /api/v1/jobs/{job}/bids/{bid}/initiate-payment`
        │
        ▼
3. Backend calculates commission and returns:
   - total payable amount (e.g. 35.00 SAR)
   - `destinations` object containing provider's `id` ("dst_...") and net share (e.g. 32.55 SAR)
        │
        ▼
4. Flutter passes `destinations` directly into Tap SDK's `tapConfigurations` map
        │
        ▼
5. Customer completes payment inside Tap Native SDK sheet
        │
        ▼
6. Tap automatically:
   - Collects 35.00 SAR from customer's card
   - Instantly splits & routes 32.55 SAR to Provider's Tap account / IBAN
   - Keeps 2.45 SAR (Azhl fee) in Azhl's main merchant account
        │
        ▼
7. SDK returns `charge_id` -> Flutter calls `POST /api/v1/jobs/{job}/bids/{bid}/verify-tap-payment`
```

---

## 2. Backend API Updates

### Step 1: Initiate Payment
- **Endpoint:** `POST /api/v1/jobs/{jobId}/bids/{bidId}/initiate-payment`
- **Headers:** `Authorization: Bearer <SANCTUM_TOKEN>`, `Accept: application/json`

#### Example Backend Response:
```json
{
  "status": 200,
  "message": "Payment initiated successfully.",
  "data": {
    "payment_id": 142,
    "amount": 35.0,
    "currency": "SAR",
    "destination_id": "dst_test_niXrKCkrlWx3K1TP",
    "provider_share": 32.55,
    "azhl_commission": 2.45,
    "destinations": {
      "destination": [
        {
          "id": "dst_test_niXrKCkrlWx3K1TP",
          "amount": 32.55,
          "currency": "SAR"
        }
      ]
    },
    "bid_price": "35.00",
    "customer_app_fee": "0.00",
    "total_payable_by_customer": "35.00",
    "payment_breakdown": {
      "subtotal": "35.00",
      "total_amount": "35.00"
    }
  }
}
```

> [!NOTE]
> If a provider has not added an IBAN or is not yet onboarded to Tap, `destinations` will safely return `null`. The payment will process through the platform account without crashing or blocking the customer.

---

## 3. Flutter Implementation (`checkout_flutter`)

### Step 2: Configure `tapConfigurations` with `destinations`

In your payment controller or view-model (e.g., `service_tracking_provider.dart`), after calling `initiate-payment`:

```dart
// 1. Call initiate-payment API
final response = await apiService.post(
  '/api/v1/jobs/$jobId/bids/$bidId/initiate-payment',
);

final data = response.data['data'];
final num totalAmount = data['amount']; // e.g. 35.00
final dynamic destinations = data['destinations']; // Provider Split Object

// 2. Prepare tapConfigurations
final Map<String, dynamic> tapConfigurations = {
  "hashString": "",
  "language": Localizations.localeOf(context).languageCode == 'ar' ? 'ar' : 'en',
  "themeMode": "light",
  "selectedCurrency": "SAR",
  "supportedCurrencies": "ALL",
  "supportedPaymentMethods": "ALL",
  "paymentType": "ALL",
  "amount": totalAmount.toStringAsFixed(2),
  "gateway": {
    "publicKey": tapPublicKey, // e.g. "pk_test_..." or "pk_live_..."
    "merchantId": tapMerchantId, // e.g. "68066910"
  },
  "operator": {
    "publicKey": tapPublicKey,
  },
  "merchant": {
    "id": tapMerchantId,
  },
  "scope": "Charge",
  "purpose": "Charge",
  "order": {
    "id": "",
    "currency": "SAR",
    "amount": totalAmount.toStringAsFixed(2),
    "description": "Payment for Job #$jobId",
    "reference": "$jobId",
  },
  "customer": {
    "firstName": customerName.isNotEmpty ? customerName : "Customer",
    "lastName": "",
    "email": customerEmail.isNotEmpty ? customerEmail : "customer@azhlksa.com",
    "phone": {
      "countryCode": "966",
      "number": customerPhoneDigits,
    },
  },
  "transaction": {
    "mode": "charge",
    "charge": {
      "saveCard": false,
      "threeDSecure": true,
      "description": "Job #$jobId Bid #$bidId Payment",
      "statementDescriptor": "AZHAL SERVICE",
      "reference": {
        "transaction": "JOB-$jobId",
        "order": "$jobId",
      },
    },
  },
  "cardOptions": {
    "showBrands": true,
    "showLoadingState": false,
    "collectHolderName": true,
    "cardNameEditable": true,
    "cardFundingSource": "all",
    "saveCardOption": "none",
    "forceLtr": false,
    "alternativeCardInputs": {"cardScanner": true, "cardNFC": true},
  },
};

// 3. ATTACH DESTINATIONS FOR INSTANT PROVIDER AUTO-SPLIT
if (destinations != null) {
  tapConfigurations["destinations"] = destinations;
}

// 4. Launch Tap Native SDK
CheckoutFlutter.startCheckout(
  tapConfigurations: tapConfigurations,
  onSuccess: (Map<dynamic, dynamic> response) async {
    final String? chargeId = response['id'] ?? response['charge_id'];
    if (chargeId != null) {
      await verifyPaymentOnBackend(chargeId, totalAmount);
    }
  },
  onError: (Map<dynamic, dynamic> error) {
    debugPrint("Tap Payment Error: $error");
    // Show error toast / dialog
  },
  onCancel: () {
    debugPrint("Tap Payment Cancelled by user");
  },
);
```

---

## 4. Verify Payment Endpoint

### Step 3: Verify & Finalize Order
Once Tap Native SDK approves the charge and returns `charge_id` (e.g. `chg_xxx`), send it to the backend:

- **Endpoint:** `POST /api/v1/jobs/{jobId}/bids/{bidId}/verify-tap-payment`
- **Body:**
```json
{
  "charge_id": "chg_xxx",
  "amount": 35.00,
  "currency": "SAR",
  "payment_type": "CARD"
}
```

#### Successful Verification Response:
```json
{
  "status": 200,
  "message": "Payment verified successfully and provider hired.",
  "data": {
    "payment_id": 142,
    "order_id": 56,
    "charge_id": "chg_xxx",
    "status": "captured",
    "payment_status": "paid",
    "payment_method": "card",
    "amount": 35.0,
    "currency": "SAR",
    "order": { ... }
  }
}
```

---

## 5. Marketplace Checkout (Products)

If using Tap Native SDK for Marketplace cart checkout:

1. Call: `POST /api/v1/marketplace/cart/initiate-payment`
2. Launch Tap Native SDK with `amount` and `destinations` (if multi-vendor split provided).
3. On SDK approval, verify:
   - **Endpoint:** `POST /api/v1/marketplace/checkout/verify-tap-payment`
   - **Body:**
   ```json
   {
     "charge_id": "chg_xxx",
     "payment_id": 45,
     "amount": 150.00,
     "currency": "SAR",
     "payment_type": "CARD"
   }
   ```

---

## 6. Summary Checklist for Flutter Developer

- [x] In `initiate-payment` call, retrieve `response['data']['destinations']`.
- [x] If `destinations != null`, add it to `tapConfigurations["destinations"] = destinations;`.
- [x] Call `CheckoutFlutter.startCheckout(tapConfigurations: tapConfigurations, ...)`.
- [x] On SDK `onSuccess`, extract `chargeId` and call `verify-tap-payment`.
- [x] In Sandbox/Test mode, use `pk_test_...`; in Live mode, use `pk_live_...`.
