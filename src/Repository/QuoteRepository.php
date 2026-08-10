<?php
// src/Repository/QuoteRepository.php

namespace App\Repository;

use App\Entity\System\Quote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    public function findActiveQuotes(): array
    {
        try {
            return $this->createQueryBuilder('q')
                ->andWhere('q.isActive = :active')
                ->setParameter('active', true)
                ->orderBy('q.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function findQuoteOfTheDay(?\DateTimeInterface $date = null): ?Quote
    {
        try {
            if ($date === null) {
                $date = new \DateTime();
            }

            // Спершу спробуємо знайти цитату з конкретною датою показу
            $quote = $this->createQueryBuilder('q')
                ->andWhere('q.displayDate = :date')
                ->andWhere('q.isActive = :active')
                ->setParameter('date', $date->format('Y-m-d'))
                ->setParameter('active', true)
                ->getQuery()
                ->getOneOrNullResult();

            if ($quote) {
                return $quote;
            }

            // Якщо немає цитати на конкретну дату, повернемо випадкову активну цитату
            $allQuotes = $this->createQueryBuilder('q')
                ->andWhere('q.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getResult();

            if (empty($allQuotes)) {
                return null;
            }

            // Використовуємо день року для детермінованого вибору цитати
            $dayOfYear = (int) $date->format('z');
            $index = $dayOfYear % count($allQuotes);
            
            return $allQuotes[$index];
            
        } catch (\Exception $e) {
            return null;
        }
    }

    public function findRandomQuote(): ?Quote
    {
        try {
            $quotes = $this->findActiveQuotes();
            
            if (empty($quotes)) {
                return null;
            }

            return $quotes[array_rand($quotes)];
            
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getCategories(): array
    {
        try {
            $quotes = $this->findActiveQuotes();
            $categories = [];
            
            foreach ($quotes as $quote) {
                $category = $quote->getCategory();
                if ($category && $category !== '' && !in_array($category, $categories, true)) {
                    $categories[] = $category;
                }
            }
            
            sort($categories);
            return $categories;
            
        } catch (\Exception $e) {
            return [];
        }
    }

    public function findByCategory(string $category): array
    {
        try {
            return $this->createQueryBuilder('q')
                ->andWhere('q.category = :category')
                ->andWhere('q.isActive = :active')
                ->setParameter('category', $category)
                ->setParameter('active', true)
                ->orderBy('q.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getPopularQuotes(int $limit = 10): array
    {
        try {
            return $this->createQueryBuilder('q')
                ->andWhere('q.isActive = :active')
                ->setParameter('active', true)
                ->orderBy('q.views', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getQuoteCount(): int
    {
        try {
            return (int) $this->createQueryBuilder('q')
                ->select('COUNT(q.id)')
                ->andWhere('q.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function findLatestQuotes(int $limit = 5): array
    {
        try {
            return $this->createQueryBuilder('q')
                ->andWhere('q.isActive = :active')
                ->setParameter('active', true)
                ->orderBy('q.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function save(Quote $entity, bool $flush = false): void
    {
        try {
            $this->getEntityManager()->persist($entity);

            if ($flush) {
                $this->getEntityManager()->flush();
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function remove(Quote $entity, bool $flush = false): void
    {
        try {
            $this->getEntityManager()->remove($entity);

            if ($flush) {
                $this->getEntityManager()->flush();
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }
}