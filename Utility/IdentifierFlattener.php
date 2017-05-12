<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */


namespace Bankiru\Api\Doctrine\Utility;

use Bankiru\Api\Doctrine\ApiEntityManager;
use Bankiru\Api\Doctrine\Mapping\ApiMetadata;
use Bankiru\Api\Doctrine\Mapping\EntityMetadata;
use Bankiru\Api\Doctrine\UnitOfWork;

/**
 * The IdentifierFlattener utility now houses some of the identifier manipulation logic from unit of work, so that it
 * can be re-used elsewhere.
 *
 * @since       2.5
 * @author      Rob Caiger <rob@clocal.co.uk>
 */
final class IdentifierFlattener
{
    /** @var UnitOfWork */
    private $unitOfWork;
    /** @var  ApiEntityManager */
    private $manager;

    /**
     * Initializes a new IdentifierFlattener instance, bound to the given EntityManager.
     *
     * @param ApiEntityManager $manager
     *
     * @internal param UnitOfWork $unitOfWork
     * @internal param ClassMetadataFactory $metadataFactory
     */
    public function __construct(ApiEntityManager $manager)
    {
        $this->manager    = $manager;
        $this->unitOfWork = $this->manager->getUnitOfWork();
    }

    /**
     * convert foreign identifiers into scalar foreign key values to avoid object to string conversion failures.
     *
     * @param ApiMetadata $class
     * @param array       $id
     *
     * @return array
     */
    public function flattenIdentifier(ApiMetadata $class, array $id)
    {
        $flatId = [];

        foreach ($class->getIdentifierFieldNames() as $field) {
            if ($class->hasAssociation($field) && array_key_exists($field, $id) && is_object($id[$field])) {
                /* @var EntityMetadata $targetClassMetadata */
                $targetClassMetadata = $this->manager->getClassMetadata(
                    $class->getAssociationMapping($field)['targetEntity']
                );

                if ($this->unitOfWork->isInIdentityMap($id[$field])) {
                    $associatedId =
                        $this->flattenIdentifier(
                            $targetClassMetadata,
                            $this->unitOfWork->getEntityIdentifier($id[$field])
                        );
                } else {
                    $associatedId =
                        $this->flattenIdentifier(
                            $targetClassMetadata,
                            $targetClassMetadata->getIdentifierValues($id[$field])
                        );
                }

                $flatId[$field] = implode(' ', $associatedId);
            } else {
                $flatId[$field] = $id[$field];
            }
        }

        return $flatId;
    }
}
