## App Features & Business Logic

### 1. Order Management (`orders.php`)

* **Order Creation & Workflow:**
* Allows creating new orders with dynamic line items (product selection and quantity).
* Automatically calculates total revenue, total cost, and net profit in real time on the frontend and server side.
* Generates a unique, formatted order number: `KKL-YYMMDD-XXXX` (e.g., `KKL-261008-0001`).


* **Price Snapshotting Logic:**
* When an order is saved, the product's unit cost and selling price are copied to `order_items`. Modifying product catalog prices later will not retroactively alter saved orders.


* **Status Lifecycle:**
* Orders cycle through three statuses: `Pending` $\rightarrow$ `Completed` $\rightarrow$ `Cancelled` $\rightarrow$ `Pending`.
* Quick-status toggle button on the order list for single-click workflow updates.


* **Customer Auto-Link/Creation:**
* Selecting "+ New customer" creates a new customer record upon saving or links to an existing customer if the phone number already exists in the database.


* **Order Editing:**
* Preserves existing price snapshots for already-ordered items while allowing item addition/removal, customer assignment, channel changes, status updates, and order date adjustments.



---

### 2. Product Catalog (`products.php`)

* **Catalog Management:**
* Supports bilingual product details (Myanmar & English names, descriptions, burn times).
* Tracks cost price (`original_price_mmk`, `original_price_usd`) and selling price (`selling_price_mmk`, `selling_price_usd`).
* Classifies products into three fixed categories: `Small`, `Normal`, `Large`.


* **Margin & Profit Indicators:**
* Displays cost, selling price, profit per unit ($Profit = Selling - Cost$), and profit margin percentage ($Margin = \frac{Profit}{Selling} \times 100$) per product card.
* Shows a real-time margin preview calculation when editing prices in the modal.
* Displays total catalog average profit margin percentage.


* **Availability & Bestseller Rules:**
* Toggle products as `Bestseller` or `Available`.
* Products marked as **Unavailable** are hidden from selection when creating *new* orders, but remain preserved on past historical orders.



---

### 3. Customer CRM (`customers.php`)

* **Customer Directory & Search:**
* Lists customers with live client-side search filtering by name or phone number.
* Tracks preferred ordering channels (`Viber`, `Telegram`, `In-Store`, `Website`).


* **KPI Metrics:**
* Calculates **Total Customers**, **Repeat Customers** (customers with $\ge 2$ orders), **Average Order Value (AOV)**, and **Top Preferred Channel**.


* **Order History & Lifetime Value:**
* Customer detail modal displays complete purchasing history, order statuses, totals, and links to receipts.
* Lifetime revenue and lifetime profit metrics on the CRM dashboard sum **Completed** orders only.


* **Data Consistency:**
* Phone numbers are unique per customer (`UNIQUE` database constraint).
* Updating a customer's name or phone number runs a transaction that cascades changes to the customer headers in all past `orders`.



---

### 4. Reports & Analytics (`report.php`)

* **Time-Based Filtering:**
* Supports date range filters: `All Time`, `Today`, `This Week`, `This Month`.


* **Revenue & Profit Reporting:**
* Displays high-level KPIs: **Total Revenue**, **Cost of Goods Sold (COGS)**, **Net Profit**, and overall **Profit Margin %**.
* All metrics strictly include **Completed** orders (excluding `Pending` and `Cancelled`).


* **Category & Product Performance:**
* **Category Profitability Table:** Itemized breakdown of units sold, gross revenue, cost, net profit, and margin per product category (`Small`, `Normal`, `Large`).
* **Top 5 Products Table:** Ranks the top 5 profit-generating products for the selected period.


* **Monthly Analytics Chart:**
* Interactive Chart.js bar chart displaying month-by-month comparative trends for Revenue vs. Net Profit over the last 12 months.



---

### 5. Receipt System (`receipt.php`)

* **Multi-Format Receipt Viewer:**
* Generates formatted receipts with bilingual shop branding (Kaung Kan Lin Candle Shop / ကောင်းကံလင်း).
* Dual printing modes: **Standard (A5/Full page)** and **80mm Thermal Roll Paper**.


* **Export & Sharing Tools:**
* **Copy Text:** Formats and copies a plain-text receipt directly to the clipboard.
* **Save Image:** Uses `html2canvas` to generate downloadable PNG receipts or trigger native Web Share API dialogs.
* **Print / Save PDF:** Optimized CSS print layout that strips out navigation/buttons during print mode.
* Displays status warning banners if an order is still `Pending` or has been `Cancelled`.



---

### 6. Security & System Utilities (`dbconfig.php` & `nav.php`)

* **CSRF Protection:** Form submissions require and validate cryptographically secure session CSRF tokens (`csrf_token()`, `csrf_check()`).
* **XSS Prevention:** Output escaping function (`e()`) applied across HTML templates.
* **Flash Messaging:** Session-based flash alerts for user feedback on actions (creates, updates, errors).
* **Database Transactions:** Uses PDO transactions with rollback handling during multi-step order and customer operations to guarantee data integrity.
