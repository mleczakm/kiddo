<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LlmTxtController extends AbstractController
{
    #[Route('/llm.txt', name: 'app_llm_txt', methods: ['GET'])]
    public function index(UrlGeneratorInterface $urlGenerator): Response
    {
        $mcpUrl = $urlGenerator->generate('_mcp_endpoint', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $content = <<<EOF
            # Warsztatownia Sensoryczna - Kiddo

            > Creative sensory workshop studio for children in Radzymin, Poland. We organize educational, creative, and engaging activities (sensory play, sensoplasty) that stimulate sensory development in young children.

            ## Contact & Location
            - Address: Aleja Jana Pawła II 12D, 05-250 Radzymin, Poland
            - Email: warsztatownia.sensoryczna@gmail.com
            - Phone: +48 571 531 213

            ## Model Context Protocol (MCP) Server

            This platform provides an MCP server that enables AI agents to check available workshops, schedules, and make bookings on behalf of users.

            ### Connection Details
            - **Transport**: HTTP (JSON-RPC over HTTP)
            - **Endpoint**: {$mcpUrl}
            - **Protocol Version**: 2026-07-28
            - **Authentication**:
              - Public access (no service key required)
              - Rate limited: 100 requests per hour per IP
              - User identity via `X-Kiddo-Chat-Token: <chat-token>` header (for authenticated operations)
              - Guests obtain a chat token by calling `user_register` (or `user_request_login_code` for an existing account) followed by `user_login_with_code` with the 6-digit code emailed to them — no password involved

            ### Capabilities

            The server exposes the following MCP capabilities:

            #### Tools
            Functions that AI models can invoke to perform actions:

            **User Tools (parent-facing):**
            - `user_register`: Register a new parent account (email, name, phone optional). Emails a 6-digit verification code — never call this back to the user yourself, ask them to read it off their inbox
            - `user_request_login_code`: Request a login verification code by email for an existing account. Rate limited: 3 per hour per email
            - `user_login_with_code`: Exchange email + the 6-digit code for a chat token, completing registration or login
            - `user_me`: Return logged-in parent profile (name, email, phone, children_count)
            - `user_update_profile`: Update parent name, email and/or phone (requires confirm=true)
            - `user_list_children`: List children (id, name, birthday) on the parent account
            - `user_add_child`: Add a child to the parent account (requires confirm=true)
            - `user_delete_child`: Delete a child from the parent account (requires confirm=true)
            - `user_list_upcoming_lessons`: List available workshops/lessons for browsing or booking. Optional filters: age (years), week (Monday YYYY-MM-DD), query, limit. Public — works for guests
            - `user_get_lesson`: Get one workshop/lesson details, seats and ticket options
            - `user_create_booking`: Book a workshop for the logged-in parent. Returns BLIK payment instructions (requires confirm=true)
            - `user_get_payment_instructions`: BLIK-to-phone payment details for a pending booking
            - `user_list_bookings`: List parent bookings, optionally filtered by status
            - `user_get_booking`: Get one booking owned by the parent
            - `user_list_carnets`: List multi-lesson (carnet) bookings for the parent
            - `user_booking_reschedule_options`: List alternative dates when rescheduling an existing booking
            - `user_reschedule_lesson`: Reschedule a booked lesson to another lesson in the series (requires confirm=true)
            - `user_cancel_lesson`: Cancel a lesson on a booking without refund (requires confirm=true)
            - `user_request_refund`: Request a refund for a paid lesson on a booking (requires confirm=true)
            - `user_list_notifications`: List recent in-app notifications for the parent
            - `user_mark_notification_read`: Mark a notification as read
            - `user_delete_notification`: Soft-delete a notification (requires confirm=true)

            **Admin Tools (admin-facing):**
            - `admin_today_schedule`: List today's lessons with attendee counts
            - `admin_list_lessons`: List lessons for a week (admin ops)
            - `admin_get_lesson`: Get lesson details for admin ops
            - `admin_toggle_lesson`: Toggle lesson status between active and cancelled (requires confirm=true)
            - `admin_update_lesson_capacity`: Set lesson capacity (spots) (requires confirm=true)
            - `admin_list_series`: List series in a date range
            - `admin_update_series`: Replace ticket options for a series (requires confirm=true)
            - `admin_clone_template_lesson`: Create a new lesson occurrence by cloning a template lesson (requires confirm=true)
            - `admin_list_bookings`: Search bookings by status or user email fragment
            - `admin_create_booking`: Create a booking for a user; requires ticket_type and payment=paid|send_code|on_site (always ask the admin how it is paid), optional price_override (requires confirm=true)
            - `admin_mark_booking_paid`: Mark booking payment as paid (requires confirm=true)
            - `admin_cancel_lesson`: Cancel a lesson on any booking (admin) (requires confirm=true)
            - `admin_refund_lesson`: Refund a lesson on any booking (admin) (requires confirm=true)
            - `admin_reschedule_lesson`: Reschedule any booking lesson (admin) (requires confirm=true)
            - `admin_list_payments`: List pending payments, optional search
            - `admin_list_unmatched_transfers`: List bank transfers not yet assigned to a payment
            - `admin_assign_transfer`: Assign an unmatched transfer to a pending payment and mark paid (requires confirm=true)
            - `admin_reject_transfer`: Reject/delete an unmatched transfer (requires confirm=true)

            ### MCP Client Configuration Example

            ```json
            {
              "mcpServers": {
                "kiddo": {
                  "transport": {
                    "type": "http",
                    "url": "{$mcpUrl}"
                  }
                }
              }
            }
            ```

            ### Domain Knowledge

            Key concepts for understanding the system:

            1. **Lesson/Workshop**: Specific scheduled activity with date, time, capacity, and ticket options
            2. **Series**: A collection of related lessons (e.g., a multi-week course)
            3. **Booking**: Reservation tying a user to a lesson with a specific ticket type (one_time or carnet_4)
            4. **Payment**: BLIK-based payment system with ~24h validity for pending bookings
            5. **Carnet**: Multi-lesson pass (e.g., carnet_4 for 4 entries) with rescheduling flexibility
            6. **Child Profile**: Each child must be registered on the parent account before booking

            ### AI Agent Behavior Guidelines

            When assisting users:
            - Always ask for child's age before suggesting workshops (age groups are strictly defined)
            - Use a warm, friendly tone (we serve families with young children)
            - For booking actions, always obtain explicit user confirmation before calling mutation tools
            - When lessons are fully booked, suggest waitlist options and alternative dates
            - Call `user_list_upcoming_lessons` for browsing the catalog, not rescheduling tools
            - Use `user_me` instead of asking for personal data already stored on the account
            - Never repeat a verification code back to the user — it is only valid because it was delivered to their own inbox; just ask them to read it to you
            EOF;

        return new Response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
