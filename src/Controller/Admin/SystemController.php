<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Form\Admin\SettingsType;
use App\Payment\PaymentGateways;
use App\Service\FailedMessages;
use App\Service\SiteSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Site settings, undelivered emails and the application log.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class SystemController extends AbstractController
{
    #[Route('/settings', name: 'app_admin_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, SiteSettings $settings, PaymentGateways $payments): Response
    {
        $form = $this->createForm(SettingsType::class, $settings->all());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $settings->save($data);
            $this->addFlash('success', 'admin.settings.flash.saved');

            return $this->redirectToRoute('app_admin_settings');
        }

        return $this->render('admin/settings.html.twig', ['form' => $form, 'payments_enabled' => $payments->isEnabled()]);
    }

    #[Route('/emails', name: 'app_admin_failed_messages', methods: ['GET'])]
    public function failedMessages(FailedMessages $failedMessages): Response
    {
        return $this->render('admin/failed_messages.html.twig', ['messages' => $failedMessages->all()]);
    }

    #[Route('/emails/{id}/retry', name: 'app_admin_failed_message_retry', methods: ['POST'])]
    public function retry(string $id, Request $request, FailedMessages $failedMessages): Response
    {
        if ($this->isCsrfTokenValid('failed-'.$id, $request->getPayload()->getString('_token')) && $failedMessages->retry($id)) {
            $this->addFlash('success', 'admin.emails.flash.retried');
        }

        return $this->redirectToRoute('app_admin_failed_messages');
    }

    #[Route('/emails/{id}/delete', name: 'app_admin_failed_message_delete', methods: ['POST'])]
    public function deleteMessage(string $id, Request $request, FailedMessages $failedMessages): Response
    {
        if ($this->isCsrfTokenValid('failed-'.$id, $request->getPayload()->getString('_token')) && $failedMessages->delete($id)) {
            $this->addFlash('success', 'admin.emails.flash.deleted');
        }

        return $this->redirectToRoute('app_admin_failed_messages');
    }

    #[Route('/logs', name: 'app_admin_logs', methods: ['GET'])]
    public function logs(Request $request): Response
    {
        $file = \sprintf('%s/%s.log', $this->getParameter('kernel.logs_dir'), $this->getParameter('kernel.environment'));
        $lines = max(50, min(2000, $request->query->getInt('lines', 200)));
        $level = $request->query->getString('level');

        $entries = is_file($file) ? $this->tail($file, $lines) : [];
        if ('' !== $level) {
            $needle = '.'.mb_strtoupper($level).':';
            $entries = array_values(array_filter($entries, static fn (string $line): bool => str_contains($line, $needle)));
        }

        return $this->render('admin/logs.html.twig', [
            'entries' => array_reverse($entries),
            'file_exists' => is_file($file),
            'file_name' => basename($file),
            'lines' => $lines,
            'level' => $level,
        ]);
    }

    /**
     * Last lines of a (possibly huge) file without reading all of it.
     *
     * @return list<string>
     */
    private function tail(string $file, int $lines): array
    {
        $handle = fopen($file, 'r');
        if (false === $handle) {
            return [];
        }

        $buffer = '';
        $chunk = 8192;
        fseek($handle, 0, \SEEK_END);
        $position = ftell($handle) ?: 0;

        while ($position > 0 && substr_count($buffer, "\n") <= $lines) {
            $read = min($chunk, $position);
            $position -= $read;
            fseek($handle, $position);
            $buffer = fread($handle, $read).$buffer;
        }
        fclose($handle);

        $result = array_values(array_filter(explode("\n", $buffer), static fn (string $l): bool => '' !== trim($l)));

        return \array_slice($result, -$lines);
    }
}
