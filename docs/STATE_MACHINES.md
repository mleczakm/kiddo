# State machines

Every status lifecycle in the app is a Symfony Workflow `state_machine` declared in
`config/packages/workflow.yaml` - that file is the single source of truth. This page is
generated from it - re-run the command below after changing a workflow.

```bash
for w in booking payment booking_occurrence lesson waitlist_entry subscription refund_request customer_order cart promotion_redemption pricing_rule; do
  docker compose run --rm php bin/console workflow:dump $w --dump-format=mermaid
done
```

## How to work with them

- **Change a lifecycle**: edit `workflow.yaml` (places / transitions) and nothing else decides legality.
- **Drive a transition**: inject `App\Application\Workflow\StateMachinesInterface` and call `can()` / `apply()` /
  `applyIfPossible()` (the last one reproduces the old "silent no-op if illegal" behaviour).
  `Booking` and `Payment` keep their dedicated ports (`BookingStateMachineInterface`, `PaymentStateMachineInterface`).
- **Never** compare `->status` to string literals to decide whether something is allowed, and never call the
  `setStatus()` marking-store hooks directly - they only stamp bookkeeping (timestamps) for the transition.
- **Extra data** for a transition goes through workflow context (`['now' => ...]`) or a typed `record*()` call
  made right after `apply()` (e.g. `RefundRequest::recordDecision()`).
- **Test** with `tests/Application/Workflow/StateMachinesTest.php`.

## Not modelled as workflows (on purpose)

- `User` account lifecycle (`AccountLifecycle`): derived from nullable timestamps, there is no status column to mark.
- `Post` (`PostStatus`): a two-state draft/published toggle with no guards.
- `LessonModal::$paymentStatus`: transient UI state of one Live Component, not persisted domain state.

## Known gaps

- `Booking::STATUS_WAITING_APPROVAL` exists (approval flow, `Booking::approve()`) but is not a place of the `booking` workflow.
- `AdminChatTools` still calls `Booking::setStatus()` / `Payment::setStatus()` as a fallback when the workflow refuses.
- `AdminPricingRulesComponent`'s edit form assigns `PricingRule::$status` directly (any state to any state, so harmless today).

## Booking (`booking`)

Customer reservation (guards + notifications; port `BookingStateMachineInterface`)

```mermaid
graph LR
place0(["pending"])
place1(("active"))
place2(("cancelled"))
place3(("past"))
place0-->|"confirm"|place1
place0-->|"cancel"|place2
place1-->|"cancel"|place2
place2-->|"reactivate"|place1
place1-->|"request_refund"|place1
place1-->|"attend"|place1
place1-->|"complete"|place3
place1-->|"reschedule"|place1
```

## Payment (`payment`)

Money owed/received (audit trail + metrics; port `PaymentStateMachineInterface`)

```mermaid
graph LR
place0(["pending"])
place1(("paid"))
place2(("failed"))
place3(("refund_requested"))
place4(("refunded"))
place5(("cancelled"))
place6(("expired"))
place0-->|"pay"|place1
place0-->|"fail"|place2
place1-->|"request_refund"|place3
place1-->|"refund"|place4
place3-->|"refund"|place4
place3-->|"decline_refund"|place1
place0-->|"cancel"|place5
place1-->|"cancel"|place5
place3-->|"cancel"|place5
place0-->|"expire"|place6
```

## BookingOccurrence (`booking_occurrence`)

One lesson inside a booking. Driven from inside the `Booking` aggregate, so its methods keep their own guards; `StateMachinesTest` pins them to this definition

```mermaid
graph LR
place0(["reserved"])
place1(("confirmed"))
place2(("attended"))
place3(("cancelled"))
place4(("rescheduled"))
place0-->|"confirm"|place1
place0-->|"attend"|place2
place1-->|"attend"|place2
place0-->|"cancel"|place3
place1-->|"cancel"|place3
place0-->|"reschedule"|place4
place1-->|"reschedule"|place4
place3-->|"reactivate"|place0
place4-->|"reactivate"|place0
```

## Lesson (`lesson`)

Staff cancel / re-open a lesson (`ToggleLessonCancellation`)

```mermaid
graph LR
place0(["active"])
place1(("cancelled"))
place0-->|"cancel"|place1
place1-->|"reactivate"|place0
```

## WaitlistEntry (`waitlist_entry`)

Per-lesson FIFO queue; an `offered` seat is held for `OFFER_TTL_MINUTES`

```mermaid
graph LR
place0(["waiting"])
place1(("offered"))
place2(("claimed"))
place3(("expired"))
place4(("canceled"))
place0-->|"offer"|place1
place0-->|"claim"|place2
place1-->|"claim"|place2
place1-->|"expire"|place3
place0-->|"cancel"|place4
place1-->|"cancel"|place4
```

## Subscription (`subscription`)

Monthly ticket (flag `subscriptions`, off on prod)

```mermaid
graph LR
place0(["active"])
place1(("paused"))
place2(("ended"))
place0-->|"pause"|place1
place1-->|"resume"|place0
place0-->|"end"|place2
place1-->|"end"|place2
```

## RefundRequest (`refund_request`)

Admin decision on a refund; decided exactly once

```mermaid
graph LR
place0(["pending"])
place1(("approved"))
place2(("declined"))
place0-->|"approve"|place1
place0-->|"decline"|place2
```

## CustomerOrder (`customer_order`)

Commerce order; `pay` is accepted late, `expire` never overrides `paid`

```mermaid
graph LR
place0(("pending"))
place1(["placed"])
place2(("paid"))
place3(("cancelled"))
place4(("expired"))
place0-->|"pay"|place2
place1-->|"pay"|place2
place3-->|"pay"|place2
place4-->|"pay"|place2
place0-->|"expire"|place4
place1-->|"expire"|place4
place3-->|"expire"|place4
```

## Cart (`cart`)

Customer cart; `abandoned` is stored vocabulary nothing transitions into yet

```mermaid
graph LR
place0(["open"])
place1(("converted"))
place2(("abandoned"))
place0-->|"convert"|place1
```

## PromotionRedemption (`promotion_redemption`)

Promo-code usage hold: consumed when the order is paid, released when it is lost

```mermaid
graph LR
place0(["reserved"])
place1(("consumed"))
place2(("released"))
place0-->|"consume"|place1
place0-->|"release"|place2
```

## PricingRule (`pricing_rule`)

Admin enables / disables a rule

```mermaid
graph LR
place0(["active"])
place1(("disabled"))
place0-->|"disable"|place1
place1-->|"enable"|place0
```
