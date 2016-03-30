<?php

/*
 * This file is part of the EasyAdminBundle.
 *
 * (c) Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JavierEguiluz\Bundle\EasyAdminBundle\Search;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class QueryBuilder
{
    /** @var Registry */
    private $doctrine;

    public function __construct(Registry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * Creates the query builder used to get all the records displayed by the
     * "list" view.
     *
     * @param array  $entityConfig
     * @param string $sortDirection
     * @param string $sortField
     * @param string $scope
     *
     * @return DoctrineQueryBuilder
     */
    public function createListQueryBuilder(array $entityConfig, $sortField, $sortDirection, $scope)
    {
        /** @var EntityManager */
        $em = $this->doctrine->getManagerForClass($entityConfig['class']);
        /** @var DoctrineQueryBuilder */
        $queryBuilder = $em->createQueryBuilder()
            ->select('entity')
            ->from($entityConfig['class'], 'entity')
        ;

        $scopeFilter = false;
        foreach ($entityConfig['list']['scopes'] as $listScope) {
            if ($listScope['id'] === $scope) {
                $scopeFilter = $listScope['filter'];
            }
        }

        if (false !== $scopeFilter) {
            $queryBuilder->andWhere('('.$scopeFilter.')');
        }

        if (null !== $sortField) {
            $queryBuilder->orderBy('entity.'.$sortField, $sortDirection);
        }

        return $queryBuilder;
    }

    /**
     * Creates the query builder used to get the results of the search query
     * performed by the user in the "search" view.
     *
     * @param array $entityConfig
     * @param $searchQuery
     * @param $sortField
     * @param $sortDirection
     * @param $scope
     *
     * @return DoctrineQueryBuilder
     */
    public function createSearchQueryBuilder(array $entityConfig, $searchQuery, $sortField, $sortDirection, $scope)
    {
        /** @var EntityManager */
        $em = $this->doctrine->getManagerForClass($entityConfig['class']);
        /** @var DoctrineQueryBuilder */
        $queryBuilder = $em->createQueryBuilder()
            ->select('entity')
            ->from($entityConfig['class'], 'entity')
        ;

        $scopeFilter = false;
        foreach ($entityConfig['list']['scopes'] as $listScope) {
            if ($listScope['id'] === $scope) {
                $scopeFilter = $listScope['filter'];
            }
        }

        $searchExpressions = array();
        $queryParameters = array();
        foreach ($entityConfig['search']['fields'] as $name => $metadata) {
            $isNumericField = in_array($metadata['dataType'], array('integer', 'number', 'smallint', 'bigint', 'decimal', 'float'));
            $isTextField = in_array($metadata['dataType'], array('string', 'text', 'guid'));
            $searchField = sprintf('entity.%s', $name);

            if ($isNumericField && is_numeric($searchQuery)) {
                $searchExpressions[] = $queryBuilder->expr()->eq($searchField, ':exact_query');
                // adding '0' turns the string into a numeric value
                $queryParameters['exact_query'] = 0 + $searchQuery;
            } elseif ($isTextField) {
                $searchExpressions[] = $queryBuilder->expr()->like($searchField, ':fuzzy_query');
                $queryParameters['fuzzy_query'] = '%'.$searchQuery.'%';

                $searchExpressions[] = $queryBuilder->expr()->in($searchField, ':words_query');
                $queryParameters['words_query'] = explode(' ', $searchQuery);
            }
        }

        if (false !== $scopeFilter) {
            if (0 === count($searchExpressions)) {
                $queryBuilder->andWhere('('.$scopeFilter.')');
            } else {
                $searchExpression = $queryBuilder->expr()->orX();
                $searchExpression->addMultiple($searchExpressions);

                $queryBuilder->andWhere($queryBuilder->expr()->andX('('.$scopeFilter.')', $searchExpression));
                $queryBuilder->setParameters($queryParameters);
            }
        } else if (0 !== count($searchExpressions)) {
            $searchExpression = $queryBuilder->expr()->orX();
            $searchExpression->addMultiple($searchExpressions);

            $queryBuilder->andWhere($searchExpression);
            $queryBuilder->setParameters($queryParameters);
        }

        if (null !== $sortField) {
            $queryBuilder->orderBy('entity.'.$sortField, $sortDirection);
        }

        return $queryBuilder;
    }
}
