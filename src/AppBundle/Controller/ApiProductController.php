<?php
namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations as Rest; 
use AppBundle\Entity\Product;
use AppBundle\Form\ApiProductType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use AppBundle\Entity\User;

class ApiProductController extends Controller
{
    /**
     * @Rest\View(serializerGroups={"allProducts"})
     * @Rest\Get("/api/products")
     */
    public function getProductsAction(Request $request)
    {
        $products = $this->get('doctrine.orm.entity_manager')
            ->getRepository('AppBundle:Product')
            ->findAll();
        /* @var $products Product[] */
        
        return $products;
    }


    /**
     * @Rest\View(serializerGroups={"oneProduct"})
     * @Rest\Get("/api/products/{product_id}")
     */
    public function getProductAction(Request $request)
    {
        $product = $this->get('doctrine.orm.entity_manager')
            ->getRepository('AppBundle:Product')
            ->find($request->get('product_id'));
        /* @var $product Product */

        if (empty($product)) {
            return new JsonResponse(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return $product;
    }
    /**
     * @Security("is_granted('ROLE_USER')")
     * @Rest\View(statusCode=Response::HTTP_CREATED, serializerGroups={"oneProduct"})
     * @Rest\Post("/api/products")
     */
    public function postProductsAction(Request $request)
    {
        $product = new Product();
        $form = $this->createForm(ApiProductType::class, $product);
        $form->submit($request->request->all());
        if ($form->isValid()) {
            $user = $this->get('security.token_storage')->getToken()->getUser();
            $product->setUser($user);
            $em = $this->get('doctrine.orm.entity_manager');
            $startDate = new \DateTime('now');
            $endDate = new \DateTime('+14 day');
            $product->setCreatedAt($startDate)->setAutoDeleteAt($endDate);
            $em->persist($product);
            $em->flush();
            return $product;
        } else {
            return $form;
        }
    }
    /**
     * @Security("is_granted('ROLE_ADMIN') or product.getUser() == user")
     * @Rest\View(statusCode=Response::HTTP_NO_CONTENT)
     * @Rest\Delete("/api/products/{id}")
     */
    public function removeProductAction(Request $request, Product $product)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $product = $em->getRepository('AppBundle:Product')
            ->find($request->get('id'));
        /* @var $product Product */

        if ($product) {
            $em->remove($product);
            $em->flush();
        }
    }
    /**
     * @Security("is_granted('ROLE_ADMIN') or product.getUser() == user")
     * @Rest\View()
     * @Rest\Put("/api/products/{id}")
     */
    public function updateProductAction(Request $request, Product $product)
    {
        return $this->updateProduct($request, true);
    }


    /**
     * @Security("is_granted('ROLE_ADMIN') or product.getUser() == user")
     * @Rest\View(serializerGroups={"oneProduct"})
     * @Rest\Patch("/api/products/{id}")
     */
    public function patchProductAction(Request $request, Product $product)
    {
        return $this->updateProduct($request, false);
    }

    private function updateProduct(Request $request, $clearMissing)
    {
        $product = $this->get('doctrine.orm.entity_manager')
            ->getRepository('AppBundle:Product')
            ->find($request->get('id'));
        /* @var $product Product */

        if (empty($product)) {
            return new JsonResponse(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        $form = $this->createForm(ApiProductType::class, $product);

        $form->submit($request->request->all(), $clearMissing);

        if ($form->isValid()) {
            $em = $this->get('doctrine.orm.entity_manager');

            $em->merge($product);
            $em->flush();
            return $product;
        } else {
            return $form;
        }
    }
}