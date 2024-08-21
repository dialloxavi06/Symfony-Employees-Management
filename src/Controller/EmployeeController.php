<?php

namespace App\Controller;

use App\Repository\EmployeeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;





class EmployeeController extends AbstractController
{
    // Route pour la page d'index
    #[Route('/employee', name: 'employee_index')]
    public function index(): Response
    {
        return $this->render('employee/index.html.twig', [
            'controller_name' => 'EmployeeController',
        ]);
    }

    // Route pour obtenir tous les employés
    #[Route('api/v1/employee', name: 'get_all_employees', methods: ['GET'])]
    public function getAllEmployees(EmployeeRepository $employeeRepository): JsonResponse
    {
        $employees = $employeeRepository->findAll();
        $data = [];

        foreach ($employees as $employee) {
            $data[] = [
                'id' => $employee->getId(),
                'firstName' => $employee->getFirstName(),
                'lastName' => $employee->getLastName(),
                'email' => $employee->getEmail(),
                'phone' => $employee->getPhone(),
            ];
        }

        return new JsonResponse($data, Response::HTTP_OK);
    }

    // Route pour obtenir un employé par ID
    #[Route('api/v1/employee/{id}', name: 'get_employee_by_id', methods: ['GET'])]
    public function getEmployeeById($id, EmployeeRepository $employeeRepository): JsonResponse
    {
        $employee = $employeeRepository->findOneBy(['id' => $id]);

        if (!$employee) {
            return new JsonResponse(['error' => 'Employee not found'], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $employee->getId(),
            'firstName' => $employee->getFirstName(),
            'lastName' => $employee->getLastName(),
            'email' => $employee->getEmail(),
            'phone' => $employee->getPhone(),
        ];

        return new JsonResponse($data, Response::HTTP_OK);
    }

    // Route pour ajouter un employé
    #[Route('api/v1/employee', name: 'add_employee', methods: ['POST'])]
    public function addEmployee(Request $request, EmployeeRepository $employeeRepository, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validation des données
            if (!isset($data['firstName'], $data['lastName'], $data['email'], $data['phone'])) {
                return new JsonResponse(['error' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
            }

            $firstName = $data['firstName'];
            $lastName = $data['lastName'];
            $email = $data['email'];
            $phone = $data['phone'];

            // Vérification si l'email existe déjà
            $existingEmployee = $employeeRepository->findOneBy(['email' => $email]);

            if ($existingEmployee) {
                return new JsonResponse(['error' => 'Email already exists'], Response::HTTP_BAD_REQUEST);
            }

            // Ajouter l'employé
            $employee = new Employee();
            $employee->setFirstName($firstName);
            $employee->setLastName($lastName);
            $employee->setEmail($email);
            $employee->setPhone($phone);
            $em->persist($employee);
            $em->flush();





            return new JsonResponse(['status' => 'Employee added'], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An error occurred: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Route pour mettre à jour un employé
    #[Route('api/v1/employee/{id}', name: 'update_employee', methods: ['PUT'])]
    public function updateEmployee($id, Request $request, EmployeeRepository $employeeRepository, EntityManagerInterface $em): JsonResponse
    {
        try {
            $employee = $employeeRepository->findOneBy(['id' => $id]);

            if (!$employee) {
                return new JsonResponse(['error' => 'Employee not found'], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            // Validation des données
            if (!isset($data['firstName'], $data['lastName'], $data['email'], $data['phone'])) {
                return new JsonResponse(['error' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
            }

            $firstName = $data['firstName'];
            $lastName = $data['lastName'];
            $email = $data['email'];
            $phone = $data['phone'];

            // Vérification si l'email existe déjà
            $existingEmployee = $employeeRepository->findOneBy(['email' => $email]);

            if ($existingEmployee && $existingEmployee->getId() != $id) {
                return new JsonResponse(['error' => 'Email already exists'], Response::HTTP_BAD_REQUEST);
            }

            // Mettre à jour l'employé
            $employee->setFirstName($firstName);
            $employee->setLastName($lastName);
            $employee->setEmail($email);
            $employee->setPhone($phone);
            $em->persist($employee);
            $em->flush();

            return new JsonResponse(['status' => 'Employee updated'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'An error occurred: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



    // Route pour supprimer un employé

    #[Route('api/v1/employee/{id}', name: 'delete_employee', methods: ['DELETE'])]
    public function deleteEmployee($id, EmployeeRepository $employeeRepository, EntityManagerInterface $em): JsonResponse
    {
        $employee = $employeeRepository->findOneBy(['id' => $id]);

        if (!$employee) {
            return new JsonResponse(['error' => 'Employee not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($employee);
        $em->flush();

        return new JsonResponse(['status' => 'Employee deleted'], Response::HTTP_OK);
    }

    // Route pour télécharger une photo d'employé

    #[Route('api/v1/employee/upload-photo', name: 'upload_employee_photo', methods: ['POST'])]
    public function uploadPhoto(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['image'])) {
            return new JsonResponse(['error' => 'No image data provided'], Response::HTTP_BAD_REQUEST);
        }

        $imageData = $data['image'];

        if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
            $type = strtolower($type[1]); // png, jpg, jpeg

            if (!in_array($type, ['jpg', 'jpeg', 'png'])) {
                return new JsonResponse(['error' => 'Invalid image type'], Response::HTTP_BAD_REQUEST);
            }

            $imageData = base64_decode($imageData);

            if ($imageData === false) {
                return new JsonResponse(['error' => 'Base64 decode failed'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            return new JsonResponse(['error' => 'Did not match data URI with image data'], Response::HTTP_BAD_REQUEST);
        }

        $fileName = bin2hex(random_bytes(10)) . '.' . $type;
        $photosDirectory = $this->getParameter('photos_directory');

        if (!is_dir($photosDirectory) && !mkdir($photosDirectory, 0777, true) && !is_dir($photosDirectory)) {
            return new JsonResponse(['error' => 'Failed to create directory for photos'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $filePath = $photosDirectory . '/' . $fileName;

        try {
            file_put_contents($filePath, $imageData);
        } catch (FileException $e) {
            return new JsonResponse(['error' => 'Failed to save image: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $photoUrl = $this->getParameter('photos_base_url') . '/' . $fileName;

        return new JsonResponse(['url' => $photoUrl], Response::HTTP_CREATED);
    }


    #[Route('api/v1/employee/uploads/photos', name: 'get_employee_photos', methods: ['GET'])]
    public function getPhotos(): JsonResponse
    {
        $photosDirectory = $this->getParameter('photos_directory');

        dump($photosDirectory);

        if (!is_dir($photosDirectory)) {
            return new JsonResponse(['error' => 'Photos directory does not exist'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $photosBaseUrl = $this->getParameter('photos_base_url');

        $photos = array_diff(scandir($photosDirectory), ['.', '..']);

        $photoUrls = [];
        foreach ($photos as $photo) {
            $photoUrls[] = [
                'url' => $photosBaseUrl . '/' . $photo,
            ];
        }

        return new JsonResponse($photoUrls, Response::HTTP_OK);
    }
}
