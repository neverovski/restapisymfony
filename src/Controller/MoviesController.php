<?php

namespace App\Controller;

use App\Entity\Movie;
use App\Entity\Role;
use App\Entity\EntityMerger;
use App\Resource\Filtering\Movie\MovieFilterDefinitionFactory;
use App\Resource\Filtering\Role\RoleFilterDefinitionFactory;
use App\Resource\Pagination\Movie\MoviePagination;
use App\Resource\Pagination\PageRequestFactory;
use App\Resource\Pagination\Role\RolePagination;
use FOS\HttpCacheBundle\Configuration\InvalidateRoute;
use FOS\RestBundle\Controller\ControllerTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use App\Exception\ValidationException;

/**
 * @Security("is_anonymous() or is_authenticated()")
 */
class MoviesController extends AbstractController
{
    use ControllerTrait;

    /**
     * @var EntityMerger
     */
    private $entityMerger;

    /**
     * @var MoviePagination
     */
    private $moviePagination;

    /**
     * @var RolePagination
     */
    private $rolePagination;

    /**
     * MoviesController constructor.
     * @param EntityMerger $entityMerger
     * @param MoviePagination $moviePagination
     * @param RolePagination $rolePagination
     */
    public function __construct(
        EntityMerger $entityMerger,
        MoviePagination $moviePagination,
        RolePagination $rolePagination
    )
    {
        $this->entityMerger = $entityMerger;
        $this->moviePagination = $moviePagination;
        $this->rolePagination = $rolePagination;
    }

    /**
     * @Rest\View()
     */
    public function getMoviesAction(Request $request)
    {
        $pageRequestFactory = new PageRequestFactory();
        $page = $pageRequestFactory->fromRequest($request);

        $movieFilterDefinitionFactory = new MovieFilterDefinitionFactory();
        $movieFilterDefinition = $movieFilterDefinitionFactory->factory($request);

        return $this->moviePagination->paginate($page, $movieFilterDefinition);
    }

    /**
     * @Rest\View(statusCode=201)
     * @ParamConverter("movie", converter="fos_rest.request_body")
     * @Rest\NoRoute()
     */
    public function postMoviesAction(Movie $movie, ConstraintViolationListInterface $validationErrors)
    {
        if (count($validationErrors) > 0) {
            throw new ValidationException($validationErrors);
        }
        $manager = $this->getDoctrine()->getManager();

        $manager->persist($movie);
        $manager->flush();

        return $movie;
    }

    /**
     * @Rest\View()
     * @InvalidateRoute("get_movie", params={"movie" = {"expression" = "movie.getId()"}})
     * @InvalidateRoute("get_movies")
     */
    public function deleteMovieAction(?Movie $movie)
    {
        if (null === $movie) {
            return $this->view(null, 404);
        }

        $manager = $this->getDoctrine()->getManager();
        $manager->remove($movie);
        $manager->flush();
    }

    /**
     * @Rest\View()
     * @Cache(public=true, maxage=3600, smaxage=3600)
     */
    public function getMovieAction(?Movie $movie)
    {
        if (null === $movie) {
            return $this->view(null, 404);
        }

        return $movie;
    }

    /**
     * @Rest\View()
     */
    public function getMovieRolesAction(Request $request, Movie $movie)
    {
        $pageRequestFactory = new PageRequestFactory();
        $page = $pageRequestFactory->fromRequest($request);

        $roleFilterDefinitionFactory = new RoleFilterDefinitionFactory();
        $roleFilterDefinition = $roleFilterDefinitionFactory->factory(
            $request,
            $movie->getId()
        );

        return $this->rolePagination->paginate($page, $roleFilterDefinition);
    }

    /**
     * @Rest\View(statusCode=201)
     * @ParamConverter("role", converter="fos_rest.request_body", options={"deserializationContext"={"groups"={"Deserialize"}}})
     * @Rest\NoRoute()
     */
    public function postMovieRolesAction(Movie $movie, Role $role, ConstraintViolationListInterface $validationErrors)
    {
        if (count($validationErrors) > 0) {
            throw new ValidationException($validationErrors);
        }

        $role->setMovie($movie);
        $manager = $this->getDoctrine()->getManager();

        $manager->persist($role);
        $movie->getRoles()->add($role);

        $manager->persist($movie);
        $manager->flush();

        return $role;
    }

    /**
     * @Rest\View()
     * @ParamConverter("modifiedMovie", converter="fos_rest.request_body",
     *     options={"validator" = {"groups" = {"Patch"}}}
     * )
     * @Rest\NoRoute()
     * @Security("is_authenticated()")
     */
    public function patchMovieAction(?Movie $movie, Movie $modifiedMovie, ConstraintViolationListInterface $validationErrors)
    {
        if (null === $movie) {
            return $this->view(null, 404);
        }

        if (count($validationErrors) > 0) {
            throw new ValidationException($validationErrors);
        }

        $this->entityMerger->merge($movie, $modifiedMovie);

        $manager = $this->getDoctrine()->getManager();
        $manager->persist($movie);
        $manager->flush();

        return $movie;
    }
}
