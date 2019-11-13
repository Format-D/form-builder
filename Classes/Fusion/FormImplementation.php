<?php
declare(strict_types=1);

namespace Neos\Form\Builder\Fusion;

/*
 * This file is part of the Neos.Form.Builder package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Form\Core\Model\FormDefinition;
use Neos\Form\Core\Model\Renderable\RenderableInterface;
use Neos\Form\Exception\PresetNotFoundException;
use Neos\Fusion\Exception as FusionException;
use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Neos\Utility\Arrays;

class FormImplementation extends AbstractFusionObject
{

    /**
     * @Flow\InjectConfiguration(package="Neos.Form")
     * @var array
     */
    protected $formSettings;

    public function getPath(): string
    {
        return $this->path;
    }

    public function evaluate()
    {
        try {
            $formDefinition = $this->buildFormDefinition();
        } catch (PresetNotFoundException $exception) {
            return $exception->getMessage();
        }
        $formDefinition->setRenderingOption('_fusionRuntime', $this->runtime);
        $controllerContext = $this->runtime->getControllerContext();

        $actionRequest = $controllerContext->getRequest();
        if (!$actionRequest instanceof ActionRequest) {
            throw new \RuntimeException(sprintf('The Form ControllerContext requires an instance of ActionRequest, instance of "%s" given', get_class($controllerContext->getRequest())), 1499345873);
        }
        $formRuntime = $formDefinition->bind($actionRequest, $controllerContext->getResponse());

        $this->runtime->pushContext('formRuntime', $formRuntime);
        $this->runtime->evaluate($this->path . '/renderCallbacks');
        $this->runtime->popContext();

        return $formRuntime->render();
    }

    /**
     * @return FormDefinition
     * @throws FusionException
     * @throws PresetNotFoundException
     * @throws \Neos\Form\Exception\IdentifierNotValidException
     */
    protected function buildFormDefinition(): FormDefinition
    {
        $presetName = $this->getPresetName();
        $formDefaults = $this->getPresetConfiguration($presetName);

        $formDefinition = new FormDefinition($this->getIdentifier(), $formDefaults, $this->getFormElementType());
        foreach ($this->getRenderingOptions() as $optionName => $optionValue) {
            $formDefinition->setRenderingOption($optionName, $optionValue);
        }

        $this->runtime->pushContext('form', $formDefinition);
        $this->runtime->evaluate($this->path . '/firstPage');
        $this->runtime->evaluate($this->path . '/furtherPages');
        $this->runtime->evaluate($this->path . '/finishers');
        $this->runtime->popContext();

        /** @var RenderableInterface $renderable */
        foreach ($formDefinition->getRenderablesRecursively() as $renderable) {
            $renderable->onBuildingFinished();
        }

        return $formDefinition;
    }

    /**
     * @param string $presetName
     * @return array
     * @throws PresetNotFoundException
     */
    private function getPresetConfiguration(string $presetName): array
    {
        if (!isset($this->formSettings['presets'][$presetName])) {
            throw new PresetNotFoundException(sprintf('The Preset "%s" was not found underneath Neos: Form: presets.', $presetName), 1499240977);
        }
        $preset = $this->formSettings['presets'][$presetName];
        if (isset($preset['parentPreset'])) {
            $parentPreset = $this->getPresetConfiguration($preset['parentPreset']);
            unset($preset['parentPreset']);
            $preset = Arrays::arrayMergeRecursiveOverrule($parentPreset, $preset);
        }
        return $preset;
    }

    private function getPresetName(): string
    {
        return $this->fusionValue('presetName');
    }

    private function getIdentifier(): string
    {
        return $this->fusionValue('identifier');
    }

    private function getFormElementType(): string
    {
        $formElementType = $this->fusionValue('formElementType');
        if ($formElementType === null) {
            throw new FusionException(sprintf('Missing formElementType for Form Finisher Fusion object "%s" at "%s"', $this->fusionObjectName, $this->path), 1502465830);
        }
        return $formElementType;
    }

    private function getRenderingOptions(): array
    {
        return $this->fusionValue('renderingOptions');
    }
}
