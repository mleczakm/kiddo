<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Marks a message whose handler must NOT be wrapped in the bus-wide Doctrine transaction.
 *
 * Implement it when the handler:
 *  - waits on slow external I/O (IMAP, HTTP, SMTP) between database writes — the wrapping
 *    transaction would sit "idle in transaction" and Postgres kills the connection once
 *    idle_in_transaction_session_timeout elapses (WARSZTATOWNIA-AH), or
 *  - acknowledges something outside the database (e.g. marks a mail as seen) right after
 *    a "commit" of its own — nested inside the wrapping transaction that commit is not real,
 *    and a failure of the outer COMMIT would roll the data back after the side effect happened.
 *
 * A handler for such a message owns its transactions (short, per unit of work).
 */
interface RunsOutsideTransaction {}
