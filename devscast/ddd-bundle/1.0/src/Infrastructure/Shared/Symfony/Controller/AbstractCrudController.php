<?php

declare(strict_types=1);

namespace Infrastructure\Shared\Symfony\Controller;

use Domain\Authentication\Entity\User;
use Domain\Shared\Entity\HasIdentityInterface;
use Domain\Shared\Repository\DataRepositoryInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * class AbstractCrudController.
 *
 * @author bernard-ng <bernard@devscast.tech>
 *
 * @method User getUser()
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
abstract class AbstractCrudController extends AbstractController
{
    use DeleteCsrfTrait;

    protected const ROUTE_PREFIX = 'administration_';
    protected const DOMAIN = 'authentication';
    protected const ENTITY = 'user';

    protected readonly Request $request;

    public function __construct(
        MessageBusInterface $commandBus,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        protected readonly RequestStack $requestStack,
        protected readonly PaginatorInterface $paginator
    ) {
        parent::__construct($commandBus, $translator, $logger);
        if (null !== $this->requestStack->getCurrentRequest()) {
            $this->request = $this->requestStack->getCurrentRequest();
        }
    }

    public function getViewPath(string $name, bool $overrideFormViews = false): string
    {
        if (('new' === $name || 'edit' === $name || 'form' === $name) && false === $overrideFormViews) {
            return '@admin/shared/layout/form.html.twig';
        }

        return sprintf('@admin/domain/%s/%s/%s.html.twig', static::DOMAIN, static::ENTITY, $name);
    }

    public function getRouteName(string $name): string
    {
        return sprintf('%s%s_%s_%s', self::ROUTE_PREFIX, static::DOMAIN, static::ENTITY, $name);
    }

    public function queryIndex(DataRepositoryInterface $repository): Response
    {
        return $this->render(
            view: $this->getViewPath('index'),
            parameters: [
                'data' => $this->paginator->paginate(
                    target: $repository->findBy([]),
                    page: $this->request->query->getInt('page', 1),
                    limit: 50
                ),
            ]
        );
    }

    public function executeCommand(object $command, ?HasIdentityInterface $row = null): Response
    {
        try {
            $this->dispatchSync($command);
            $this->addSuccessfullActionFlash('annulation du bannissement');
        } catch (\Throwable $e) {
            $this->addSafeMessageExceptionFlash($e);
        }

        if (null !== $row) {
            return $this->redirectSeeOther(
                route: $this->getRouteName('show'),
                params: [
                    'id' => $row->getId(),
                ]
            );
        }

        return $this->redirectSeeOther($this->getRouteName('index'));
    }

    public function executeFormCommand(
        object $command,
        string $formClass,
        ?HasIdentityInterface $row = null,
        string $view = 'new',
        bool $overrideFormViews = false,
        ?string $redirectToPath = null,
        bool $hasIndex = true,
    ): Response {
        $turbo = $this->request->headers->get('Turbo-Frame');
        $form = $this->createForm($formClass, $command, [
            'action' => $this->generateUrl(
                route: strval($this->request->attributes->get('_route')),
                parameters: (array) $this->request->attributes->get('_route_params', []),
            ),
        ])->handleRequest($this->request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->dispatchSync($command);
                $this->addSuccessfullActionFlash();

                if (null !== $row && method_exists($row, 'getId')) {
                    return $this->redirectSeeOther(
                        route: $this->getRouteName('show'),
                        params: [
                            'id' => $row->getId(),
                        ]
                    );
                }

                return match (true) {
                    null !== $redirectToPath => new RedirectResponse($redirectToPath, Response::HTTP_SEE_OTHER),
                    default => $this->redirectSeeOther($this->getRouteName('index'))
                };
            } catch (\Throwable $e) {
                if ($turbo) {
                    $form->addError($this->addSafeMessageExceptionError($e));
                } else {
                    $this->addSafeMessageExceptionFlash($e);
                    $response = $this->createUnprocessableEntityResponse();
                }
            }
        }

        return $this->render(
            view: $this->getViewPath($view, $overrideFormViews),
            parameters: [
                'form' => $form,
                'data' => $row,
                '_domain' => static::DOMAIN,
                '_entity' => static::ENTITY,
                '_turbo_frame_target' => $turbo,
                '_index_url' => false !== $hasIndex ? $this->generateUrl($this->getRouteName('index')) : null,
                '_show_url' => null !== $row ? $this->generateUrl($this->getRouteName('show'), [
                    'id' => $row->getId(),
                ]) : null,
            ],
            response: $response ?? null
        );
    }

    public function executeDeleteCommand(object $command, object $row, ?string $redirectToPath = null): Response
    {
        if ($this->isDeleteCsrfTokenValid($row, $this->request)) {
            try {
                $this->dispatchSync($command);

                if ($this->request->isXmlHttpRequest()) {
                    return new JsonResponse(null, Response::HTTP_ACCEPTED);
                }

                $this->addSuccessfullActionFlash('suppression');
            } catch (\Throwable $e) {
                if ($this->request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'message' => $this->getSafeMessageException($e),
                    ], Response::HTTP_BAD_REQUEST);
                }

                $this->addSafeMessageExceptionFlash($e);
            }
        }

        return match (true) {
            null !== $redirectToPath => new RedirectResponse($redirectToPath, Response::HTTP_SEE_OTHER),
            default => $this->redirectSeeOther($this->getRouteName('index'))
        };
    }
}
