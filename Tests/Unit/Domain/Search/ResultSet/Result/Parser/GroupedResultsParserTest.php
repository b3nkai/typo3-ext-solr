<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Result\Parser;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\Group;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\DocumentEscapeService;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\GroupedResultParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use ApacheSolrForTypo3\Solr\Tests\Unit\SetUpUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Testcase to test the GroupedResultsParser.
 */
class GroupedResultsParserTest extends SetUpUnitTestCase
{
    protected GroupedResultParser $parser;

    protected function setUp(): void
    {
        $this->parser = new GroupedResultParser(
            $this->createMock(SearchResultBuilder::class),
            $this->createMock(DocumentEscapeService::class),
        );
        parent::setUp();
    }
    #[Test]
    public function canParsedQueryGroupResult(): void
    {
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())->method('getSearchGroupingGroupsConfiguration')->willReturn([
            'pidQuery.' => [
                'queries.' => [
                    'lessThenTen' => 'pid:[0 TO 10]',
                    'lessThen30' => 'pid:[11 TO 30]',
                    'rest' => 'pid:[30 TO *]',
                ],
            ],
        ]);
        $configurationMock->expects(self::any())->method('getSearchGroupingResultLimit')->willReturn(5);

        $resultSet = $this->getSearchResultSetMockFromConfigurationAndFixtureFileName($configurationMock, 'fake_solr_response_group_on_queries.json');

        $searchResultsSet = $this->parser->parse($resultSet);
        $searchResultsCollection = $searchResultsSet->getSearchResults();

        self::assertTrue($searchResultsCollection->getHasGroups());
        self::assertSame(1, $searchResultsCollection->getGroups()->getCount());

        $queryGroup = $searchResultsCollection->getGroups()->getByPosition(0)->getGroupItems();
        self::assertSame(5, $queryGroup->getByPosition(0)->getSearchResults()->getCount());
        self::assertSame(3, $queryGroup->getCount(), 'Unexpected amount of groups in parsing result');
    }

    #[Test]
    public function canParsedQueryFieldResult(): void
    {
        $configurationMock = $this->createMock(TypoScriptConfiguration::class);
        $configurationMock->expects(self::any())->method('getSearchGroupingGroupsConfiguration')->willReturn([
            'typeGroup.' => [
                'field' => 'type',
            ],
            'pidGroup' => [
                'field' => 'pid',
            ],
        ]);
        $configurationMock->expects(self::any())->method('getSearchGroupingResultLimit')->willReturn(5);

        $resultSet = $this->getSearchResultSetMockFromConfigurationAndFixtureFileName($configurationMock, 'fake_solr_response_group_on_fields.json');

        $searchResultsSet = $this->parser->parse($resultSet);
        $searchResultsCollection = $searchResultsSet->getSearchResults();

        self::assertTrue($searchResultsCollection->getHasGroups());
        self::assertSame(2, $searchResultsCollection->getGroups()->getCount(), 'There should be 1 Groups of search results');
        self::assertSame(2, $searchResultsCollection->getGroups()->getByPosition(0)->getGroupItems()->getCount(), 'The group should contain two group items');

        /** @var Group $firstGroup */
        $firstGroup = $searchResultsCollection->getGroups()->getByPosition(0);
        self::assertSame('typeGroup', $firstGroup->getGroupName(), 'Unexpected groupName for the first group');

        $typeGroup = $searchResultsCollection->getGroups()->getByPosition(0)->getGroupItems();
        self::assertSame('pages', $typeGroup->getByPosition(0)->getGroupValue(), 'There should be 5 documents in the group pages');
        self::assertSame(5, $typeGroup->getByPosition(0)->getSearchResults()->getCount(), 'There should be 5 documents in the group pages');

        self::assertSame('tx_news_domain_model_news', $typeGroup->getByPosition(1)->getGroupValue(), 'There should be 2 documents in the group news');
        self::assertSame(2, $typeGroup->getByPosition(1)->getSearchResults()->getCount(), 'There should be 2 documents in the group news');

        self::assertSame(10, $searchResultsCollection->getCount(), 'There should be a 7 search results when they are fetched without groups');
        self::assertSame(47, $resultSet->getAllResultCount(), 'Unexpected allResultCount');
    }

    protected function getSearchResultSetMockFromConfigurationAndFixtureFileName(
        TypoScriptConfiguration $configurationMock,
        string $fixtureName,
    ): SearchResultSet {
        $searchRequestMock = $this->createMock(SearchRequest::class);
        $searchRequestMock->expects(self::any())->method('getContextTypoScriptConfiguration')->willReturn($configurationMock);
        $resultSet = $this->getMockBuilder(SearchResultSet::class)->onlyMethods(['getUsedSearchRequest', 'getResponse'])->getMock();
        $resultSet->expects(self::any())->method('getUsedSearchRequest')->willReturn($searchRequestMock);
        $resultSet->expects(self::any())->method('getResponse')->willReturn($this->getFakeApacheSolrResponse($fixtureName));

        return $resultSet;
    }

    protected function getFakeApacheSolrResponse(string $fixtureFile): ResponseAdapter
    {
        $fakeResponseJson = self::getFixtureContentByName($fixtureFile);
        return new ResponseAdapter($fakeResponseJson);
    }
}
