# Courier parcel data normalization

## Raw table analysis

The original `courier_raw_parcels` table combines shipment, people, hub, status, and payment facts in one row:

`[tracking_number, sender_name, sender_address, recipient_name, recipient_address, parcel_weight_kg, delivery_type, shipping_cost, status_update, current_hub_name, dispatch_timestamp]`

- A parcel's sender/recipient details and hub name repeat for every parcel, creating update and deletion anomalies.
- `status_update` can repeat as a parcel moves; storing multiple statuses in one field creates a repeating group and prevents individual events from being queried reliably.
- `tracking_number` identifies a parcel, while the sender and recipient names/addresses describe customers and `current_hub_name` describes a hub. These facts have different dependencies and should not be stored together.
- The shipping charge is derived from parcel weight and delivery speed. Persisting a manually entered charge risks inconsistent totals; the service calculates and stores the result.
- Dispatch time describes a status event, not the parcel's identity. It belongs with the ordered shipment status history.

## 3NF decomposition

The normalized design uses five domain tables:

1. **customers** (`id`, `name`, `address`) — customer facts are stored once per distinct normalized name/address; parcel rows reference sender and recipient separately.
2. **transit_hubs** (`id`, `name`) — hub names are stored once and referenced from a parcel's current location and its status events.
3. **parcels** (`id`, `tracking_number`, sender/recipient/hub foreign keys, `parcel_weight_kg`, `delivery_type`, `shipping_cost`) — tracking number identifies the shipment; customer and hub descriptions are not repeated here.
4. **shipment_status_logs** (`id`, `parcel_id`, `transit_hub_id`, `sequence`, `status`, `occurred_at`) — each event is its own row; `(parcel_id, sequence)` is unique and the service appends the next sequence transactionally.
5. **courier_payments** (`id`, `parcel_id`, `amount`, `payment_status`, `payment_method`, `paid_at`) — payment facts are separated from shipment facts and can represent future payment attempts.

All non-key attributes in these tables describe the key, the whole key, and nothing but the key. Foreign keys enforce the relationships; parcel deletion cascades to its dependent event/payment records, while customer deletion is restricted and hub deletion clears optional references.

## Shipping rule

The service computes `shipping_cost = parcel_weight_kg × 5.00 × speed multiplier`, where standard is `1.0`, express is `1.5`, and overnight is `2.5`. Monetary results are rounded to two decimal places. Creating a parcel, its initial status event, and its pending payment is one database transaction.
