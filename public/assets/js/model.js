document.addEventListener('DOMContentLoaded', () =>
  {
    const provider = document.getElementById('model-provider');
    const baseModel = document.getElementById('base-model');
    const modelinfo = document.getElementById('modelinfo');

    if (!provider || !baseModel || !modelinfo)
      {
        return;
      }

    const capabilityNames = {
      vision: 'Vision',
      video: 'Video',
      audio: 'Audio',
      tools: 'Tools',
      thinking: 'Thinking'
    };

    let modelInfoRequest = 0;

    const renderModelInfo = (info) =>
      {
        const size = document.getElementById('modelinfo-size');
        const quantization = document.getElementById(
            'modelinfo-quantization'
        );
        const context = document.getElementById('modelinfo-context');
        const capabilities = document.getElementById(
            'modelinfo-capabilities'
        );

        if (size)
          {
            size.textContent = info.size || '-';
          }

        if (quantization)
          {
            quantization.textContent = info.quantization || '-';
          }

        if (context)
          {
            context.textContent = info.context
              ? Number(info.context).toLocaleString('hu-HU')
              : '-';
          }

        if (capabilities)
          {
            capabilities.replaceChildren();

            Object.entries(capabilityNames).forEach(([key, label]) =>
              {
                if (!info[key])
                  {
                    return;
                  }

                const badge = document.createElement('span');
                badge.textContent = label;
                capabilities.appendChild(badge);
              });
          }

        modelinfo.value = JSON.stringify(info);
      };

    const loadModelInfo = async () =>
      {
        const selectedProvider = provider.value;
        const selectedModel = baseModel.value;
        const requestId = ++modelInfoRequest;

        if (!selectedProvider || !selectedModel)
          {
            return;
          }

        try
          {
            const data = new FormData();
            data.append('provider', selectedProvider);
            data.append('basemodel', selectedModel);

            const response = await fetch(
                '/model_ajax/modelinfo',
                {
                  method: 'POST',
                  body: data
                }
            );
            const result = await response.json();

            if (
                requestId !== modelInfoRequest ||
                !result.success ||
                !result.modelinfo ||
                typeof result.modelinfo !== 'object'
            )
              {
                return;
              }

            renderModelInfo(result.modelinfo);
          }
        catch (error)
          {
            console.error('Provider model info error:', error);
          }
      };

    const loadModels = async (selectedProvider, previousProvider) =>
      {
        const previousValue = baseModel.value;

        provider.disabled = true;
        baseModel.disabled = true;

        try
          {
            const data = new FormData();
            data.append('provider', selectedProvider);

            const response = await fetch(
                '/model_ajax/providermodels',
                {
                  method: 'POST',
                  body: data
                }
            );
            const result = await response.json();

            if (!result.success || !Array.isArray(result.models))
              {
                provider.value = previousProvider;
                return;
              }

            baseModel.replaceChildren();

            result.models.forEach((model) =>
              {
                const option = document.createElement('option');
                option.value = model;
                option.textContent = model;
                option.selected = model === previousValue;
                baseModel.appendChild(option);
              });

            provider.dataset.previousValue = selectedProvider;
            await loadModelInfo();
          }
        catch (error)
          {
            console.error('Provider model list error:', error);
            provider.value = previousProvider;
          }
        finally
          {
            provider.disabled = false;
            baseModel.disabled = false;
          }
      };

    provider.addEventListener('change', async () =>
      {
        const selectedProvider = provider.value;
        const previousProvider = provider.dataset.previousValue
            || provider.value;

        await loadModels(selectedProvider, previousProvider);
      });

    baseModel.addEventListener('change', loadModelInfo);

    provider.dataset.previousValue = provider.value;
    loadModels(provider.value, provider.value);
  });