<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Polls the bank mailbox over IMAP. Runs outside the bus-wide transaction: the IMAP round-trips
 * would otherwise keep it idle (Postgres idle-in-transaction timeout, WARSZTATOWNIA-AH), and each
 * mail must be really committed by the importer before it is marked as seen.
 */
final class ImportTransfersFromMail implements RunsOutsideTransaction {}
