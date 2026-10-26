# Offline Cash Payment (COD) & Provider Dues — Complete Test Flow Guide

Yeh guide backend API aur Admin Panel par Offline Cash Payment (Cash on Delivery) aur Provider Dues (Commission Holding & Bidding Block) ko test karne ke liye banai gayi hai.

---

## 📌 Test Prerequisites
- **Base URL**: `https://admin.azhlksa.com/api/v1` (ya aapka local API domain)
- **Customer Token**: Sanctum Bearer Token jiska role `0` ho.
- **Provider Token**: Sanctum Bearer Token jiska role `1` ho.
- **Order ID**: Ek existing order jiska status `completed` ya provider complete stage par ho.

---

## 🔄 Complete Step-by-Step Test Flow

```
[Customer]                            [Backend]                            [Provider]
    │                                     │                                    │
    │  1. POST /orders/{id}/payment-method│                                    │
    ├────────────────────────────────────►│                                    │
    │     (payment_method: "cash")        │──► Push Notification ─────────────►│
    │                                     │   ("Customer selected Cash")       │
    │                                     │                                    │
    │                                     │  2. POST /orders/{id}/confirm-cash │
    │                                     │◄───────────────────────────────────┤
    │                                     │     (collected_amount: 105.00)     │
    │                                     │                                    │
    │◄─ Push Notification ────────────────│  3. Calculate Fees:                │
    │   ("Payment Confirmed")             │     - Wallet Deduct (if any)       │
    │                                     │     - Remaining added to dues      │
    │                                     │                                    │
    │                                     │  4. If payment_due >= 25.00 SAR:   │
    │                                     │     POST /post-bid/{job} ──► 403   │
    │                                     │     (BLOCKED from Bidding)         │
    │                                     │                                    │
    │                                     │  5. POST /provider/dues/pay        │
    │                                     │◄───────────────────────────────────┤
    │                                     │     (Pay via Tap Payment)          │
    │                                     │     (UNBLOCKED automatically)      │
```

---

## 🚀 API Execution Steps

### Step 1: Customer Cash Payment Method Select Kare

Customer order complete hone ke baad payment screen par Cash select karta hai.

* **Endpoint**: `POST /api/v1/orders/{order_id}/payment-method`
* **Headers**:
  ```http
  Authorization: Bearer <CUSTOMER_TOKEN>
  Content-Type: application/json
  Accept: application/json
  ```
* **Request Body**:
  ```json
  {
    "payment_method": "cash"
  }
  ```
* **Expected Response (200 OK)**:
  ```json
  {
    "status": 200,
    "success": true,
    "message": "Payment method updated to cash.",
    "data": {
      "order_id": 12,
      "payment_method": "cash",
      "payment_status": "cash_pending",
      "total_amount": 105.00,
      "currency": "SAR"
    }
  }
  ```
* **Action Triggered**:
  - `orders.payment_method` ban gaya `'cash'`.
  - `orders.payment_status` ban gaya `'cash_pending'`.
  - Provider ke phone par push notification send hui: *"Please collect SAR 105.00 in cash upon completing the service."*

---

### Step 2: Tracking Check Karein (Mobile App Data Verification)

Customer aur Provider dono is API se order status check kar sakte hain:

* **Endpoint**: `GET /api/v1/orders/tracking/{order_id}`
* **Headers**:
  ```http
  Authorization: Bearer <CUSTOMER_OR_PROVIDER_TOKEN>
  Accept: application/json
  ```
* **Expected Response**:
  ```json
  {
    "status": 200,
    "success": true,
    "data": [
      {
        "status": "completed",
        "order": {
          "id": 12,
          "price": "100.00",
          "total_amount": "105.00",
          "payment_method": "cash",
          "payment_status": "cash_pending",
          "is_paid": false,
          "app_service_fee": "5.00"
        }
      }
    ]
  }
  ```

---

### Step 3: Provider Physical Cash Receive Confirm Kare

Provider jab customer se cash pakad le, to app se confirmation trigger karta hai.

* **Endpoint**: `POST /api/v1/orders/{order_id}/confirm-cash`
* **Headers**:
  ```http
  Authorization: Bearer <PROVIDER_TOKEN>
  Content-Type: application/json
  Accept: application/json
  ```
* **Request Body**:
  ```json
  {
    "collected_amount": 105.00
  }
  ```
* **Expected Response (200 OK)**:
  ```json
  {
    "status": 200,
    "success": true,
    "message": "Cash payment confirmed successfully.",
    "data": {
      "order_id": 12,
      "payment_status": "paid",
      "collected_amount": 105.00,
      "platform_fee_total": 10.00,
      "deducted_from_wallet": 0.00,
      "added_to_dues": 10.00,
      "current_payment_due": 10.00
    }
  }
  ```
* **Action Triggered**:
  - `orders.payment_status` ban gaya `'paid'`.
  - `orders.paid_to_system` ban gaya `1`.
  - Platform Commission (App fee 5 SAR + Azhl fee 5 SAR = 10 SAR):
    - Agar wallet me balance tha: auto-deduct ho gaya (`deducted_from_wallet: 10.00`).
    - Agar wallet 0 tha: provider ke `payment_due` me 10.00 SAR charh gaya.
  - `provider_dues_ledger` me record save ho gaya.
  - Customer ko confirmation notification receive hui.

---

### Step 4: Provider Dues Ledger & Status Check Kare

Provider check karta hai us par kitna due baqi hai aur statement kia hai:

* **Endpoint**: `GET /api/v1/provider/dues/ledger`
* **Headers**:
  ```http
  Authorization: Bearer <PROVIDER_TOKEN>
  Accept: application/json
  ```
* **Expected Response (200 OK)**:
  ```json
  {
    "status": 200,
    "success": true,
    "data": {
      "payment_due": 10.00,
      "max_due_threshold": 25.00,
      "is_blocked": false,
      "history": {
        "data": [
          {
            "id": 1,
            "provider_id": 4,
            "order_id": 12,
            "type": "debit_cash_order",
            "amount": "10.00",
            "previous_due": "0.00",
            "current_due": "10.00",
            "notes": "Platform fee of 10 SAR for Order #12..."
          }
        ]
      }
    }
  }
  ```

---

### Step 5: Bidding Blocking Test (Security Rule Enforcement)

Jab provider ka `payment_due >= 25.00 SAR` ho jaye (e.g., 3 cash orders ke baad due 30 SAR ho gaya):

Provider kisi naye job request par bid submit karne ki koshish karta hai:

* **Endpoint**: `POST /api/v1/post-bid/{job_id}`
* **Headers**:
  ```http
  Authorization: Bearer <BLOCKED_PROVIDER_TOKEN>
  Content-Type: application/json
  Accept: application/json
  ```
* **Request Body**:
  ```json
  {
    "price": 120.00,
    "details": "I can do this repair"
  }
  ```
* **Expected Response (403 Forbidden - BLOCKED)**:
  ```json
  {
    "status": 403,
    "success": false,
    "message": "You have exceeded the maximum allowed unpaid cash commission threshold (30.00 SAR >= 25.00 SAR). Please clear your pending platform dues before accepting new jobs or placing bids.",
    "data": {
      "error_code": "PAYMENT_DUE_EXCEEDED",
      "payment_due": 30.00,
      "max_due_threshold": 25.00
    }
  }
  ```

---

### Step 6: Provider Clears Dues Online via Tap Payment

Provider unblock hone ke liye apna due amount Tap se pay karta hai:

* **Endpoint**: `POST /api/v1/provider/dues/pay`
* **Headers**:
  ```http
  Authorization: Bearer <BLOCKED_PROVIDER_TOKEN>
  Content-Type: application/json
  Accept: application/json
  ```
* **Request Body**:
  ```json
  {
    "amount": 25.00
  }
  ```
* **Expected Response (200 OK)**:
  ```json
  {
    "status": 200,
    "success": true,
    "data": {
      "payment_id": "45",
      "tap_charge_id": "chg_xxxx",
      "redirect_url": "https://checkout.payments.tap.company/...",
      "amount": 25.00,
      "currency": "SAR",
      "current_due": 30.00
    }
  }
  ```
* **Result**:
  - Provider browser/in-app webview me `redirect_url` open karke Mada/Visa card se payment karta hai.
  - Payment success hote hi Tap Webhook auto-trigger hota hai:
    - Provider ka `payment_due` reduce ho kar `5.00 SAR` reh jata hai.
    - Provider **automatically unblock** ho jata hai aur foran nayi bids submit kar sakta hai!

---

## 🖥️ Admin Panel Test Flow

1. Admin Dashboard login karein.
2. Sidebar me **Financial Management** khol kar **"Provider Dues & Commissions (COD)"** par click karein.
   - **URL**: `https://admin.azhlksa.com/provider-dues`
3. **Overview Cards Dekhein**:
   - Total unpaid dues amount (e.g., `SAR 30.00`).
   - Blocked Providers count.
4. **Provider Filter & Search**:
   - `Blocked` tab par click karein, wahan woh provider red status me show hoga.
5. **Ledger / Details**:
   - Provider ke aagay **"Ledger / Details"** button dabayein.
   - Complete audit trail nazar aye gi:
     - Kis date/time par kaunsa order cash me hua.
     - Kitna fee due hua.
     - Online Tap charge ki receipt.
6. **Admin Actions Test Karein**:
   - **Threshold Limit Barhana**: Provider ka limit 25 SAR se barha kar 50 SAR karein, provider foran unblock ho jaye ga.
   - **Manual Cash Settlement**: Agar provider office cash la kar de de, to dropdown se select karein *"Paid Cash to Office"* ya *"Clear All Dues"* aur submit karein. Dues 0 ho jaye gi aur ledger me Admin note generate ho jaye ga.
